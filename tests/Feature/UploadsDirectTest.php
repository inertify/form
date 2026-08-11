<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertify\Form\Uploads\UploadManager;
use Inertify\Form\Uploads\UploadToken;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Storage::fake('direct-uploads');

    config()->set('inertia-forms.file_uploads.direct_to_storage.disk', 'direct-uploads');
    config()->set('inertia-forms.file_uploads.direct_to_storage.multipart_threshold', 100);

    Route::inertiaFormUploads(prefix: '/test-uploads', middleware: [], name: 'test-forms');
});

it('completes a local direct upload and promotes it on the same disk', function (): void {
    $start = $this->postJson(route('test-forms.file-upload.direct.start'), [
        'name' => 'archive.txt',
        'size' => 7,
        'mimeType' => 'text/plain',
        'disk' => 'direct-uploads',
    ])->assertCreated()->assertJsonPath('mode', 'single');

    $uploadId = $start->json('uploadId');

    $this->call(
        'PUT',
        route('test-forms.file-upload.direct.object', ['uploadId' => $uploadId]),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/octet-stream'],
        'archive',
    )->assertNoContent();

    $completed = $this->postJson(route('test-forms.file-upload.direct.complete'), [
        'uploadId' => $uploadId,
    ])->assertOk()->assertJsonPath('name', 'archive.txt');

    $request = Request::create('/submit', 'POST', ['archive' => $completed->json('key')]);
    $upload = $request->formUpload('archive');

    expect($upload?->getRemoteFile()?->getDisk())->toBe('direct-uploads')
        ->and($upload?->storeAs('final', 'archive.txt', 'direct-uploads'))->toBe('final/archive.txt');

    Storage::disk('direct-uploads')->assertExists('final/archive.txt');
});

it('rejects a client-selected disk that is not bound by configuration or a rules token', function (): void {
    Storage::fake('private-disk');

    $this->postJson(route('test-forms.file-upload.direct.start'), [
        'name' => 'archive.txt',
        'size' => 7,
        'mimeType' => 'text/plain',
        'disk' => 'private-disk',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('upload');
});

it('uploads reports and completes multipart parts through the local fallback', function (): void {
    config()->set('inertia-forms.file_uploads.direct_to_storage.multipart_threshold', 5);
    config()->set('inertia-forms.file_uploads.direct_to_storage.part_size', 5);

    $start = $this->postJson(route('test-forms.file-upload.direct.start'), [
        'name' => 'multipart.txt',
        'size' => 11,
        'mimeType' => 'text/plain',
    ])->assertCreated()
        ->assertJsonPath('mode', 'multipart')
        ->assertJsonPath('partSize', 5);

    $uploadId = $start->json('uploadId');

    foreach ([1 => 'hello', 2 => ' worl', 3 => 'd'] as $partNumber => $contents) {
        $part = $this->postJson(route('test-forms.file-upload.direct.part'), [
            'uploadId' => $uploadId,
            'partNumber' => $partNumber,
        ])->assertOk()->assertJsonPath('partNumber', $partNumber);

        $this->call(
            'PUT',
            $part->json('url'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/octet-stream'],
            $contents,
        )->assertNoContent();
    }

    $this->getJson(route('test-forms.file-upload.direct.status', ['uploadId' => $uploadId]))
        ->assertOk()
        ->assertJsonPath('parts.0', ['partNumber' => 1, 'size' => 5])
        ->assertJsonPath('parts.1', ['partNumber' => 2, 'size' => 5])
        ->assertJsonPath('parts.2', ['partNumber' => 3, 'size' => 1]);

    $completed = $this->postJson(route('test-forms.file-upload.direct.complete'), [
        'uploadId' => $uploadId,
    ])->assertOk();
    $request = Request::create('/submit', 'POST', ['archive' => $completed->json('key')]);
    $remote = $request->formUpload('archive')?->getRemoteFile();

    expect($remote?->getDisk())->toBe('direct-uploads')
        ->and(Storage::disk('direct-uploads')->get((string) $remote?->getPath()))->toBe('hello world');
});

it('aborts and removes a pending local multipart upload', function (): void {
    config()->set('inertia-forms.file_uploads.direct_to_storage.multipart_threshold', 5);
    config()->set('inertia-forms.file_uploads.direct_to_storage.part_size', 5);

    $uploadId = $this->postJson(route('test-forms.file-upload.direct.start'), [
        'name' => 'aborted.txt',
        'size' => 6,
        'mimeType' => 'text/plain',
    ])->assertCreated()->json('uploadId');
    $metadata = app(UploadManager::class)->directMetadata($uploadId);

    Storage::disk('direct-uploads')->assertExists($metadata['directory'].'/.metadata.json');

    $this->deleteJson(route('test-forms.file-upload.direct.abort'), [
        'uploadId' => $uploadId,
    ])->assertNoContent();

    Storage::disk('direct-uploads')->assertMissing($metadata['directory'].'/.metadata.json');
});

it('returns validation errors for invalid rules and expired direct upload tokens', function (): void {
    $this->postJson(route('test-forms.file-upload.direct.start'), [
        'name' => 'invalid.txt',
        'size' => 7,
        'mimeType' => 'text/plain',
        'uploadRulesToken' => 'invalid-rules-token',
    ])->assertUnprocessable()->assertJsonValidationErrors('upload');

    $expired = app(UploadToken::class)->encode(
        'pending-direct-upload',
        ['disk' => 'direct-uploads'],
        now()->subSecond()->getTimestamp(),
    );

    $this->getJson(route('test-forms.file-upload.direct.status', ['uploadId' => $expired]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('upload');
});

it('continues direct cleanup after corrupt metadata and leaves failures retriable', function (): void {
    Storage::fake('direct-cleanup-temporary');
    config()->set('inertia-forms.file_uploads.temporary_uploads.disk', 'direct-cleanup-temporary');
    $manager = app(UploadManager::class);
    $first = $manager->startDirect('first.txt', 7, 'text/plain');
    $second = $manager->startDirect('second.txt', 7, 'text/plain');
    $firstMetadata = $manager->directMetadata($first['uploadId']);
    $secondMetadata = $manager->directMetadata($second['uploadId']);
    Storage::disk('direct-uploads')->put($firstMetadata['directory'].'/.metadata.json', 'invalid-json');
    $this->travel($manager->directUrlLifetime() + 1)->seconds();

    $report = $manager->cleanupReport();

    expect($report['removed'])->toBe(1)
        ->and($report['failed'])->toBe(1)
        ->and($report['errors'])->toHaveCount(1);
    Storage::disk('direct-uploads')->assertExists($firstMetadata['directory'].'/.metadata.json');
    Storage::disk('direct-uploads')->assertMissing($secondMetadata['directory'].'/.metadata.json');
});

it('retains completed direct uploads until the submitted token lifetime expires', function (): void {
    Storage::fake('direct-cleanup-temporary');
    config()->set('inertia-forms.file_uploads.temporary_uploads.disk', 'direct-cleanup-temporary');
    $manager = app(UploadManager::class);
    $start = $this->postJson(route('test-forms.file-upload.direct.start'), [
        'name' => 'retained.txt',
        'size' => 8,
        'mimeType' => 'text/plain',
    ])->assertCreated();
    $uploadId = $start->json('uploadId');
    $metadata = $manager->directMetadata($uploadId);

    $this->call(
        'PUT',
        route('test-forms.file-upload.direct.object', ['uploadId' => $uploadId]),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/octet-stream'],
        'retained',
    )->assertNoContent();
    $this->postJson(route('test-forms.file-upload.direct.complete'), [
        'uploadId' => $uploadId,
    ])->assertOk();

    $this->travel($manager->directUrlLifetime() + 1)->seconds();

    expect($manager->cleanupReport()['removed'])->toBe(0);
    Storage::disk('direct-uploads')->assertExists($metadata['final_path']);

    $this->travel($manager->lifetime())->seconds();

    expect($manager->cleanupReport()['removed'])->toBe(1);
    Storage::disk('direct-uploads')->assertMissing($metadata['final_path']);
});

it('rejects non-stream direct upload content at the manager boundary', function (): void {
    expect(fn () => app(UploadManager::class)->putDirectObject('unused', 'not-a-stream'))
        ->toThrow(InvalidArgumentException::class, 'readable stream resource');
});
