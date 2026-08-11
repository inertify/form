<?php

declare(strict_types=1);

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertify\Form\Uploads\ExistingFile;
use Inertify\Form\Uploads\SubmittedUpload;
use Inertify\Form\Uploads\UploadManager;
use Inertify\Form\Uploads\UploadRules;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Storage::fake('form-uploads');

    config()->set('inertia-forms.file_uploads.temporary_uploads.disk', 'form-uploads');

    Route::inertiaFormUploads(prefix: '/test-uploads', middleware: [], name: 'test-forms');
});

it('stores a temporary upload and resolves its encrypted token from the request', function (): void {
    $response = $this->post(route('test-forms.file-upload.store'), [
        'file' => HttpUploadedFile::fake()->createWithContent('avatar.txt', 'avatar contents'),
    ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('name', 'avatar.txt')
        ->assertJsonPath('mimeType', 'text/plain')
        ->assertJsonPath('mime_type', 'text/plain')
        ->assertJsonPath('size', 15);

    $request = Request::create('/submit', 'POST', ['avatar' => $response->json('key')]);
    $upload = $request->formUpload('avatar');

    expect($upload)->toBeInstanceOf(SubmittedUpload::class)
        ->and($upload?->isNew())->toBeTrue()
        ->and($upload?->isExisting())->toBeFalse()
        ->and($upload?->getIdentifier())->toBeString()
        ->and($upload?->getUploadedFile()?->getClientOriginalName())->toBe('avatar.txt');
});

it('materializes remote uploads inside the package storage directory', function (): void {
    Storage::set('remote-materialization', new MaterializationFakeRemoteDisk('remote contents'));

    $file = app(UploadManager::class)->materialize([
        'disk' => 'remote-materialization',
        'path' => 'remote/file.txt',
        'name' => 'file.txt',
        'mime_type' => 'text/plain',
    ]);

    expect($file->getContent())->toBe('remote contents')
        ->and($file->getClientOriginalName())->toBe('file.txt')
        ->and($file->getRealPath())->toStartWith(storage_path('inertia-forms-materialized-uploads'));
});

it('resolves multiple uploads in submitted order without mutating request input', function (): void {
    $first = $this->post(route('test-forms.file-upload.store'), [
        'file' => HttpUploadedFile::fake()->createWithContent('first.txt', 'first'),
    ])->json('key');

    $second = $this->post(route('test-forms.file-upload.store'), [
        'file' => HttpUploadedFile::fake()->createWithContent('second.txt', 'second'),
    ])->json('key');

    $request = Request::create('/submit', 'POST', ['attachments' => [$second, $first]]);
    $uploads = $request->orderedFormUploads('attachments');

    expect($uploads)->toBeInstanceOf(Collection::class)
        ->and($uploads)->toHaveCount(2)
        ->and($uploads->map(fn (SubmittedUpload $upload): string => $upload->getName())->all())
        ->toBe(['second.txt', 'first.txt'])
        ->and($request->input('attachments'))->toBe([$second, $first]);
});

it('deletes an unused temporary upload through the destroy endpoint', function (): void {
    $key = $this->post(route('test-forms.file-upload.store'), [
        'file' => HttpUploadedFile::fake()->createWithContent('unused.txt', 'unused'),
    ])->json('key');

    $request = Request::create('/submit', 'POST', ['file' => $key]);
    $path = $request->formUpload('file')?->getPath();

    expect($path)->not->toBeNull();
    Storage::disk('form-uploads')->assertExists((string) $path);

    $this->deleteJson(route('test-forms.file-upload.destroy'), ['key' => $key])
        ->assertNoContent();

    Storage::disk('form-uploads')->assertMissing((string) $path);
});

it('round trips existing file tokens without treating them as new uploads', function (): void {
    Storage::disk('form-uploads')->put('documents/report.txt', 'report');

    $existing = ExistingFile::fromDisk('form-uploads', 'documents/report.txt', withPreview: false);
    $serialized = $existing->toArray();
    $request = Request::create('/submit', 'POST', ['document' => $serialized['key']]);
    $upload = $request->formUpload('document');

    expect($serialized)->toMatchArray([
        'id' => 'form-uploads:documents/report.txt',
        'identifier' => 'form-uploads:documents/report.txt',
        'filename' => 'report.txt',
        'name' => 'report',
        'previewUrl' => null,
        'preview_url' => null,
        'size' => 6,
        'size_in_bytes' => 6,
    ])->and($upload)->toBeInstanceOf(SubmittedUpload::class)
        ->and($upload?->isExisting())->toBeTrue()
        ->and($upload?->isNew())->toBeFalse()
        ->and($upload?->getExistingFile()?->getPath())->toBe('documents/report.txt');
});

it('rejects malformed upload tokens', function (): void {
    $request = Request::create('/submit', 'POST', ['avatar' => 'not-an-upload-token']);

    expect(fn () => $request->formUpload('avatar'))
        ->toThrow(ValidationException::class);
});

it('rejects expired and tampered existing file tokens', function (): void {
    Storage::disk('form-uploads')->put('documents/expiring.txt', 'expiring');

    $expired = ExistingFile::fromDisk(
        'form-uploads',
        'documents/expiring.txt',
        expiration: now()->subSecond(),
        withPreview: false,
    );
    $expiredRequest = Request::create('/submit', 'POST', ['document' => $expired->getKey()]);

    expect(fn () => $expiredRequest->formUpload('document'))
        ->toThrow(ValidationException::class);

    $valid = ExistingFile::fromDisk('form-uploads', 'documents/expiring.txt', withPreview: false);
    $tamperedRequest = Request::create('/submit', 'POST', [
        'document' => $valid->getKey().'x',
    ]);

    expect(fn () => $tamperedRequest->formUpload('document'))
        ->toThrow(ValidationException::class);
});

it('rejects expired upload rules tokens at the upload endpoint', function (): void {
    config()->set('inertia-forms.file_uploads.temporary_uploads.lifetime', 1);
    $rulesToken = UploadRules::make(['mimes:txt'])->token();
    $this->travel(2)->seconds();

    $this->post(route('test-forms.file-upload.store'), [
        'file' => HttpUploadedFile::fake()->createWithContent('document.txt', 'document'),
        'uploadRulesToken' => $rulesToken,
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('upload');
});

final class MaterializationFakeRemoteDisk extends FilesystemAdapter
{
    public function __construct(private readonly string $contents) {}

    public function path($path)
    {
        return storage_path('remote-materialization/'.ltrim((string) $path, '/'));
    }

    public function readStream($path)
    {
        $stream = fopen('php://temp', 'w+b');

        if (! is_resource($stream)) {
            return false;
        }

        fwrite($stream, $this->contents);
        rewind($stream);

        return $stream;
    }
}
