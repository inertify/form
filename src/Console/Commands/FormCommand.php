<?php

declare(strict_types=1);

namespace Inertify\Form\Console\Commands;

use Illuminate\Console\GeneratorCommand;

class FormCommand extends GeneratorCommand
{
    protected $signature = 'make:form {name : The name of the form}';

    protected $description = 'Create a new Inertify form class';

    protected $type = 'Form';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/form.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Forms';
    }
}
