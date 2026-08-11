<?php

declare(strict_types=1);

namespace Inertify\Form\Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class BlockSetLimits implements ValidationRule
{
    /** @param array<string, int> $limits */
    public function __construct(private array $limits) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $counts = [];
        foreach ($value as $block) {
            if (is_array($block) && is_string($block['type'] ?? null)) {
                $counts[$block['type']] = ($counts[$block['type']] ?? 0) + 1;
            }
        }

        foreach ($this->limits as $type => $maximum) {
            if (($counts[$type] ?? 0) > $maximum) {
                $fail("The {$attribute} field may not contain more than {$maximum} blocks of type {$type}.");
            }
        }
    }
}
