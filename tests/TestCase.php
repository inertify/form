<?php

declare(strict_types=1);

namespace Inertify\Form\Tests;

use Inertify\Form\FormServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FormServiceProvider::class,
        ];
    }
}
