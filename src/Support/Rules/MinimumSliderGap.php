<?php

declare(strict_types=1);

namespace Inertify\Form\Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class MinimumSliderGap implements ValidationRule
{
    public function __construct(private float $minimumGap) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || count($value) !== 2 || ! is_numeric($value[0] ?? null) || ! is_numeric($value[1] ?? null)) {
            return;
        }

        if ((float) $value[1] < (float) $value[0] || (float) $value[1] - (float) $value[0] < $this->minimumGap) {
            $fail("The {$attribute} range must be ordered with a minimum gap of {$this->minimumGap}.");
        }
    }
}
