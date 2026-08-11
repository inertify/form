<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Inertify\Form\Routing\UploadRoutes;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(Router $router, UploadRoutes $uploads): void
    {
        View::addLocation(dirname(__DIR__, 2).'/resources/views');

        $uploads->register(
            $router,
            prefix: '/workbench/uploads',
            middleware: ['web'],
        );
    }
}
