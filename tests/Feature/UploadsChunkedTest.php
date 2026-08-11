<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Storage::fake('chunk-uploads');

    config()->set('inertia-forms.file_uploads.temporary_uploads.disk', 'chunk-uploads');
    config()->set('inertia-forms.file_uploads.temporary_uploads.chunked.size', 5);

    Route::inertiaFormUploads(prefix: '/test-uploads', middleware: [], name: 'test-forms');
});

it('starts appends resumes and completes a chunked upload', function (): void {
    $start = $this->postJson(route('test-forms.file-upload.chunked.start'), [
        'name' => 'video.txt',
        'size' => 11,
        'mimeType' => 'text/plain',
    ])->assertCreated()
        ->assertJsonPath('offset', 0)
        ->assertJsonPath('chunkSize', 5);

    $uploadId = $start->json('uploadId');

    $this->post(route('test-forms.file-upload.chunked.append'), [
        'uploadId' => $uploadId,
        'offset' => 0,
        'chunk' => UploadedFile::fake()->createWithContent('chunk', 'hello'),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('offset', 5);

    $this->getJson(route('test-forms.file-upload.chunked.status', ['uploadId' => $uploadId]))
        ->assertOk()
        ->assertJsonPath('offset', 5)
        ->assertJsonPath('size', 11);

    $this->post(route('test-forms.file-upload.chunked.append'), [
        'uploadId' => $uploadId,
        'offset' => 5,
        'chunk' => UploadedFile::fake()->createWithContent('chunk', ' worl'),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('offset', 10);

    $this->post(route('test-forms.file-upload.chunked.append'), [
        'uploadId' => $uploadId,
        'offset' => 10,
        'chunk' => UploadedFile::fake()->createWithContent('chunk', 'd'),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('offset', 11);

    $completed = $this->postJson(route('test-forms.file-upload.chunked.complete'), [
        'uploadId' => $uploadId,
    ])->assertOk()->assertJsonPath('name', 'video.txt');

    $request = Request::create('/submit', 'POST', ['video' => $completed->json('key')]);
    $upload = $request->formUpload('video');

    expect($upload?->getUploadedFile()?->get())->toBe('hello world');
});

it('rejects out of order chunks', function (): void {
    $uploadId = $this->postJson(route('test-forms.file-upload.chunked.start'), [
        'name' => 'video.txt',
        'size' => 5,
        'mimeType' => 'text/plain',
    ])->json('uploadId');

    $this->post(route('test-forms.file-upload.chunked.append'), [
        'uploadId' => $uploadId,
        'offset' => 2,
        'chunk' => UploadedFile::fake()->createWithContent('chunk', 'hello'),
    ], ['Accept' => 'application/json'])->assertUnprocessable();
});
