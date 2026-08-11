<?php

declare(strict_types=1);

namespace Inertify\Form\Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ValidLinkUrl implements ValidationRule
{
    /** @param list<string> $schemes */
    public function __construct(private array $schemes, private bool $requiresScheme) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        preg_match('/^([a-z][a-z0-9+.-]*):/i', $value, $matches);
        $scheme = isset($matches[1]) ? strtolower($matches[1]) : null;

        if ($scheme !== null && ! in_array($scheme, $this->schemes, true)) {
            $fail("The {$attribute} scheme is not allowed.");

            return;
        }

        if ($scheme === null && $this->requiresScheme) {
            $fail("The {$attribute} must include a URL scheme.");

            return;
        }

        if ($scheme === 'mailto' || $scheme === 'tel') {
            if (trim(substr($value, strlen($scheme) + 1)) === '') {
                $fail("The {$attribute} must be a valid URL.");
            }

            return;
        }

        $candidate = $scheme === null ? 'https://'.$value : $value;

        if (filter_var($candidate, FILTER_VALIDATE_URL) === false) {
            $fail("The {$attribute} must be a valid URL.");
        }
    }
}
