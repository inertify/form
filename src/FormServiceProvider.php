<?php

declare(strict_types=1);

namespace Inertify\Form;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Inertify\Form\Console\Commands\CleanupUploadsCommand;
use Inertify\Form\Console\Commands\FormCommand;
use Inertify\Form\Contracts\BuildsUploadDescriptors;
use Inertify\Form\Contracts\HasUploads;
use Inertify\Form\Routing\UploadRoutes;
use Inertify\Form\Uploads\SubmittedUpload;
use Inertify\Form\Uploads\UploadManager;
use Inertify\Form\Uploads\UploadResolver;
use Inertify\Form\Uploads\UploadRouteDescriptor;
use Inertify\Form\Uploads\UploadToken;

class FormServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/inertia-forms.php', 'inertia-forms');

        $this->app->singleton(UploadToken::class);
        $this->app->singleton(UploadManager::class);
        $this->app->singleton(UploadResolver::class);
        $this->app->singleton(UploadRoutes::class);
        $this->app->singleton(BuildsUploadDescriptors::class, UploadRouteDescriptor::class);
    }

    public function boot(): void
    {
        $this->registerMacros();
        $this->registerUploadHydration();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/inertia-forms.php' => config_path('inertia-forms.php'),
        ], ['inertify-form', 'inertify-form-config', 'inertia-forms-config']);

        $this->commands([
            FormCommand::class,
            CleanupUploadsCommand::class,
        ]);
    }

    private function registerMacros(): void
    {
        if (! Router::hasMacro('inertiaFormUploads')) {
            Router::macro('inertiaFormUploads', function (
                ?string $prefix = null,
                array|string|null $middleware = null,
                ?string $name = null,
            ): void {
                /** @var Router $this */
                app(UploadRoutes::class)->register($this, $prefix, $middleware, $name);
            });
        }

        if (! Request::hasMacro('formUpload')) {
            Request::macro('formUpload', function (string $key): ?SubmittedUpload {
                /** @var Request $this */
                return app(UploadResolver::class)->one($this, $key);
            });
        }

        if (! Request::hasMacro('orderedFormUploads')) {
            Request::macro('orderedFormUploads', function (string $key) {
                /** @var Request $this */
                return app(UploadResolver::class)->ordered($this, $key);
            });
        }
    }

    private function registerUploadHydration(): void
    {
        $this->app->resolving(HasUploads::class, function (HasUploads $request): void {
            if ($request instanceof Request) {
                $this->app->make(UploadResolver::class)->hydrate($request);
            }
        });
    }
}
