<?php

declare(strict_types=1);

namespace Inertify\Form\Routing;

use Illuminate\Routing\Router;
use Inertify\Form\Http\Controllers\ChunkedUploadController;
use Inertify\Form\Http\Controllers\DirectUploadController;
use Inertify\Form\Http\Controllers\TemporaryUploadController;

final class UploadRoutes
{
    /**
     * @param  array<int, string>|string|null  $middleware
     */
    public function register(
        Router $router,
        ?string $prefix = null,
        array|string|null $middleware = null,
        ?string $name = null,
    ): void {
        $prefix ??= (string) config('inertia-forms.file_uploads.route_prefix', '/_inertia-forms');
        $middleware ??= config('inertia-forms.file_uploads.middleware', ['web', 'auth']);
        $name ??= (string) config('inertia-forms.file_uploads.route_name', 'inertia-forms.');
        $name = trim($name, '.').'.';

        $router->group([
            'prefix' => trim($prefix, '/'),
            'middleware' => $middleware,
            'as' => $name,
        ], static function (Router $router): void {
            $router->post('file-upload', [TemporaryUploadController::class, 'store'])
                ->name('file-upload.store');
            $router->delete('file-upload', [TemporaryUploadController::class, 'destroy'])
                ->name('file-upload.destroy');

            $router->post('file-upload/chunked/start', [ChunkedUploadController::class, 'start'])
                ->name('file-upload.chunked.start');
            $router->get('file-upload/chunked/status', [ChunkedUploadController::class, 'status'])
                ->name('file-upload.chunked.status');
            $router->post('file-upload/chunked/chunk', [ChunkedUploadController::class, 'chunk'])
                ->name('file-upload.chunked.append');
            $router->post('file-upload/chunked/complete', [ChunkedUploadController::class, 'complete'])
                ->name('file-upload.chunked.complete');
            $router->delete('file-upload/chunked/abort', [ChunkedUploadController::class, 'abort'])
                ->name('file-upload.chunked.abort');

            $router->post('file-upload/direct/start', [DirectUploadController::class, 'start'])
                ->name('file-upload.direct.start');
            $router->put('file-upload/direct/object', [DirectUploadController::class, 'object'])
                ->name('file-upload.direct.object');
            $router->post('file-upload/direct/part', [DirectUploadController::class, 'part'])
                ->name('file-upload.direct.part');
            $router->get('file-upload/direct/status', [DirectUploadController::class, 'status'])
                ->name('file-upload.direct.status');
            $router->post('file-upload/direct/complete', [DirectUploadController::class, 'complete'])
                ->name('file-upload.direct.complete');
            $router->delete('file-upload/direct/abort', [DirectUploadController::class, 'abort'])
                ->name('file-upload.direct.abort');
        });

        // This macro can be invoked after Laravel's normal route-registration
        // bootstrap, so refresh the lookup that fluent route names populate.
        $router->getRoutes()->refreshNameLookups();
    }
}
