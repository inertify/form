<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

class Toggle extends Checkbox
{
    public function onValue(mixed $value): static
    {
        $this->trueValue($value);

        return $this->option('onValue', $value);
    }

    public function offValue(mixed $value): static
    {
        $this->falseValue($value);

        return $this->option('offValue', $value);
    }

    public function onLabel(?string $label): static
    {
        return $this->option('onLabel', $label);
    }

    public function offLabel(?string $label): static
    {
        return $this->option('offLabel', $label);
    }
}
