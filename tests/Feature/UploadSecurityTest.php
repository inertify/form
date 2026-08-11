<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertify\Form\Contracts\ValidatesFileUploads;
use Inertify\Form\Uploads\TemporaryUpload;
use Inertify\Form\Uploads\UploadManager;
use Inertify\Form\Uploads\UploadRules;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('g', 32)));
    Storage::fake('guarded-chunks');
    Storage::fake('guarded-direct');

    config()->set('inertia-forms.file_uploads.temporary_uploads.disk', 'guarded-chunks');
    config()->set('inertia-forms.file_uploads.temporary_uploads.max_size', 1);
    config()->set('inertia-forms.file_uploads.temporary_uploads.chunked.size', 1024);
    config()->set('inertia-forms.file_uploads.temporary_uploads.chunked.max_size', 4);
    config()->set('inertia-forms.file_uploads.direct_to_storage.disk', 'guarded-direct');
    config()->set('inertia-forms.file_uploads.direct_to_storage.max_size', 4);
    config()->set('inertia-forms.file_uploads.direct_to_storage.multipart_threshold', 4096);

    UploadStrategyValidator::$validated = [];
    Route::inertiaFormUploads(prefix: '/guarded-uploads', middleware: [], name: 'guarded-forms');
});

it('uses each upload strategy maximum while preserving profile and custom validation', function (): void {
    $contents = str_repeat('a', 2048);
    $rulesToken = UploadRules::make(
        ['mimetypes:text/plain', 'max:3'],
        [UploadStrategyValidator::class],
    )->token();

    $this->post(route('guarded-forms.file-upload.store'), [
        'file' => UploadedFile::fake()->createWithContent('ordinary.txt', $contents),
        'uploadRulesToken' => $rulesToken,
    ], ['Accept' => 'application/json'])->assertUnprocessable();

    $chunkId = $this->postJson(route('guarded-forms.file-upload.chunked.start'), [
        'name' => 'chunked.txt',
        'size' => strlen($contents),
        'mimeType' => 'text/plain',
        'uploadRulesToken' => $rulesToken,
    ])->assertCreated()->json('uploadId');

    foreach (str_split($contents, 1024) as $offset => $chunk) {
        $this->post(route('guarded-forms.file-upload.chunked.append'), [
            'uploadId' => $chunkId,
            'offset' => $offset * 1024,
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $chunk),
        ], ['Accept' => 'application/json'])->assertOk();
    }

    $this->postJson(route('guarded-forms.file-upload.chunked.complete'), [
        'uploadId' => $chunkId,
    ])->assertOk();

    $directId = $this->postJson(route('guarded-forms.file-upload.direct.start'), [
        'name' => 'direct.txt',
        'size' => strlen($contents),
        'mimeType' => 'text/plain',
        'uploadRulesToken' => $rulesToken,
    ])->assertCreated()->json('uploadId');

    $this->call(
        'PUT',
        route('guarded-forms.file-upload.direct.object', ['uploadId' => $directId]),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/octet-stream'],
        $contents,
    )->assertNoContent();

    $this->postJson(route('guarded-forms.file-upload.direct.complete'), [
        'uploadId' => $directId,
    ])->assertOk();

    expect(UploadStrategyValidator::$validated)->toBe(['temporary', 'direct']);
});

it('makes completed chunk sessions terminal without invalidating the final token', function (): void {
    $chunkId = $this->postJson(route('guarded-forms.file-upload.chunked.start'), [
        'name' => 'terminal.txt',
        'size' => 8,
        'mimeType' => 'text/plain',
    ])->assertCreated()->json('uploadId');

    foreach ([0 => 'orig', 4 => 'inal'] as $offset => $chunk) {
        $this->post(route('guarded-forms.file-upload.chunked.append'), [
            'uploadId' => $chunkId,
            'offset' => $offset,
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $chunk),
        ], ['Accept' => 'application/json'])->assertOk();
    }

    $key = $this->postJson(route('guarded-forms.file-upload.chunked.complete'), [
        'uploadId' => $chunkId,
    ])->assertOk()->json('key');

    $this->getJson(route('guarded-forms.file-upload.chunked.status', ['uploadId' => $chunkId]))
        ->assertUnprocessable();
    $this->post(route('guarded-forms.file-upload.chunked.append'), [
        'uploadId' => $chunkId,
        'offset' => 0,
        'chunk' => UploadedFile::fake()->createWithContent('chunk', 'evil'),
    ], ['Accept' => 'application/json'])->assertUnprocessable();
    $this->postJson(route('guarded-forms.file-upload.chunked.complete'), ['uploadId' => $chunkId])
        ->assertUnprocessable();
    $this->deleteJson(route('guarded-forms.file-upload.chunked.abort'), ['uploadId' => $chunkId])
        ->assertUnprocessable();

    $upload = Request::create('/submit', 'POST', ['file' => $key])->formUpload('file');

    expect($upload?->getUploadedFile()?->getContent())->toBe('original');
});

it('isolates finalized direct content and rejects every completed session replay', function (): void {
    $start = $this->postJson(route('guarded-forms.file-upload.direct.start'), [
        'name' => 'terminal.txt',
        'size' => 8,
        'mimeType' => 'text/plain',
    ])->assertCreated();
    $uploadId = $start->json('uploadId');
    $pending = app(UploadManager::class)->directMetadata($uploadId);

    $this->call(
        'PUT',
        route('guarded-forms.file-upload.direct.object', ['uploadId' => $uploadId]),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/octet-stream'],
        'original',
    )->assertNoContent();

    $key = $this->postJson(route('guarded-forms.file-upload.direct.complete'), [
        'uploadId' => $uploadId,
    ])->assertOk()->json('key');
    $upload = Request::create('/submit', 'POST', ['file' => $key])->formUpload('file');
    $finalPath = (string) $upload?->getPath();

    expect($finalPath)->not->toBe($pending['path']);
    Storage::disk('guarded-direct')->assertMissing($pending['path']);

    $this->call(
        'PUT',
        route('guarded-forms.file-upload.direct.object', ['uploadId' => $uploadId]),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'],
        'tampered',
    )->assertUnprocessable();
    $this->postJson(route('guarded-forms.file-upload.direct.part'), [
        'uploadId' => $uploadId,
        'partNumber' => 1,
    ])->assertUnprocessable();
    $this->getJson(route('guarded-forms.file-upload.direct.status', ['uploadId' => $uploadId]))
        ->assertUnprocessable();
    $this->postJson(route('guarded-forms.file-upload.direct.complete'), ['uploadId' => $uploadId])
        ->assertUnprocessable();
    $this->deleteJson(route('guarded-forms.file-upload.direct.abort'), ['uploadId' => $uploadId])
        ->assertUnprocessable();

    expect(Storage::disk('guarded-direct')->get($finalPath))->toBe('original');

    Storage::disk('guarded-direct')->put($finalPath, 'tampered');

    expect(fn () => Request::create('/submit', 'POST', ['file' => $key])->formUpload('file'))
        ->toThrow(ValidationException::class);
});

final class UploadStrategyValidator implements ValidatesFileUploads
{
    /** @var list<string> */
    public static array $validated = [];

    public function validate(
        TemporaryUpload $upload,
        UploadedFile $file,
        UploadRules $rules,
        Request $request,
    ): void {
        if ($file->getSize() !== 2048 || $rules->rules() !== ['mimetypes:text/plain', 'max:3']) {
            throw new RuntimeException('The strategy upload was not validated with its content profile.');
        }

        self::$validated[] = $upload->getKind();
    }
}
