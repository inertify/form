<?php

declare(strict_types=1);

namespace Inertify\Form\Support\Rules;

use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class DateNotDisabled implements ValidationRule
{
    /** @param list<string> $disabledDates */
    public function __construct(private string $format, private array $disabledDates) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $date = DateTimeImmutable::createFromFormat('!'.$this->format, $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return;
        }

        if (in_array($date->format('Y-m-d'), $this->disabledDates, true)) {
            $fail("The selected {$attribute} date is unavailable.");
        }
    }
}
