<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

class Submit extends Field
{
    public static function make(string $label = 'Submit', ?string $name = null): static
    {
        return parent::make($name ?? '_submit', $label)->withoutModelBinding();
    }

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function value(mixed $value): static
    {
        return $this->option('value', $value);
    }
}
