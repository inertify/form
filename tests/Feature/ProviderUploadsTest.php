<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Inertify\Form\Fields\File;

it('merges upload configuration and registers macros without loading routes', function (): void {
    expect(config('inertia-forms.file_uploads.route_prefix'))->toBe('/_inertia-forms')
        ->and(config('inertia-forms.file_uploads.temporary_uploads.lifetime'))->toBe(3600)
        ->and(Request::hasMacro('formUpload'))->toBeTrue()
        ->and(Request::hasMacro('orderedFormUploads'))->toBeTrue()
        ->and(Route::hasMacro('inertiaFormUploads'))->toBeTrue()
        ->and(Route::has('inertia-forms.file-upload.store'))->toBeFalse();
});

it('registers upload routes only when the route macro is invoked', function (): void {
    Route::inertiaFormUploads(prefix: '/test-uploads', middleware: [], name: 'test-forms');

    expect(Route::has('test-forms.file-upload.store'))->toBeTrue()
        ->and(Route::has('test-forms.file-upload.destroy'))->toBeTrue()
        ->and(Route::has('test-forms.file-upload.chunked.start'))->toBeTrue()
        ->and(Route::has('test-forms.file-upload.chunked.status'))->toBeTrue()
        ->and(Route::has('test-forms.file-upload.chunked.append'))->toBeTrue()
        ->and(Route::has('test-forms.file-upload.chunked.complete'))->toBeTrue()
        ->and(Route::has('test-forms.file-upload.chunked.abort'))->toBeTrue()
        ->and(Route::has('test-forms.file-upload.direct.start'))->toBeTrue()
        ->and(Route::has('test-forms.file-upload.direct.object'))->toBeTrue()
        ->and(Route::has('test-forms.file-upload.direct.part'))->toBeTrue()
        ->and(Route::has('test-forms.file-upload.direct.status'))->toBeTrue()
        ->and(Route::has('test-forms.file-upload.direct.complete'))->toBeTrue()
        ->and(Route::has('test-forms.file-upload.direct.abort'))->toBeTrue()
        ->and(route('test-forms.file-upload.store', absolute: false))->toBe('/test-uploads/file-upload');
});

it('serializes store-with-form fields without registering upload routes', function (): void {
    $field = File::make('attachment')->storeWithForm()->toArray();

    expect($field)
        ->storeWithForm->toBeTrue()
        ->temporaryUploadUrl->toBeNull()
        ->temporaryUploadDeleteUrl->toBeNull()
        ->chunkedUrls->toBeNull()
        ->directUploadUrls->toBeNull()
        ->and($field['upload'])
        ->strategy->toBe('form')
        ->endpoints->toBe([]);
});

it('serializes complete custom upload transports without package routes', function (): void {
    $storeOnly = File::make('avatar')
        ->temporaryUploadUrl('/custom/avatar')
        ->toArray();

    expect($storeOnly)
        ->temporaryUploadUrl->toBe('/custom/avatar')
        ->temporaryUploadDeleteUrl->toBeNull()
        ->and($storeOnly['upload']['endpoints'])
        ->store->toBe(['url' => '/custom/avatar', 'method' => 'POST'])
        ->not->toHaveKey('destroy');

    $customUrls = File::make('attachment')
        ->temporaryUploadUrl('/custom/uploads')
        ->temporaryUploadDeleteUrl('/custom/uploads/delete')
        ->toArray();

    expect($customUrls)
        ->temporaryUploadUrl->toBe('/custom/uploads')
        ->temporaryUploadDeleteUrl->toBe('/custom/uploads/delete')
        ->and($customUrls['upload']['endpoints'])
        ->store->url->toBe('/custom/uploads')
        ->destroy->url->toBe('/custom/uploads/delete');

    $descriptor = [
        'strategy' => 'temporary',
        'endpoints' => [
            'store' => ['method' => 'POST', 'url' => '/descriptor/uploads'],
            'destroy' => ['method' => 'DELETE', 'url' => '/descriptor/uploads'],
        ],
        'limits' => ['maxSizeKiB' => 2048],
        'disk' => null,
        'rulesToken' => null,
        'requiresRulesToken' => false,
    ];

    expect(File::make('document')->upload(fn () => $descriptor)->toArray()['upload'])
        ->toBe($descriptor);
});

it('registers package commands while running in console', function (): void {
    expect(Artisan::all())->toHaveKeys([
        'make:form',
        'form:cleanup-uploads',
    ]);
});
