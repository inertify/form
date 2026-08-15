<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

class Hidden extends Field
{
    public function getComponent(): string
    {
        return 'Hidden';
    }
}
