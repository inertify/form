<?php

declare(strict_types=1);

namespace Inertify\Form\Support\Rules;

use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ValidTimeValue implements ValidationRule
{
    /**
     * @param  list<int>  $disabledHours
     * @param  list<int>  $disabledMinutes
     * @param  list<int>  $disabledSeconds
     * @param  list<string>  $disabledValues
     */
    public function __construct(
        private string $format,
        private ?string $minimum,
        private ?string $maximum,
        private array $disabledHours,
        private array $disabledMinutes,
        private array $disabledSeconds,
        private array $disabledValues,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $time = DateTimeImmutable::createFromFormat('!'.$this->format, $value);

        if ($time === false) {
            return;
        }

        $minimum = $this->minimum === null ? false : DateTimeImmutable::createFromFormat('!'.$this->format, $this->minimum);
        $maximum = $this->maximum === null ? false : DateTimeImmutable::createFromFormat('!'.$this->format, $this->maximum);

        if (($minimum !== false && $time < $minimum)
            || ($maximum !== false && $time > $maximum)
            || in_array((int) $time->format('G'), $this->disabledHours, true)
            || in_array((int) $time->format('i'), $this->disabledMinutes, true)
            || in_array((int) $time->format('s'), $this->disabledSeconds, true)
            || in_array($value, $this->disabledValues, true)) {
            $fail("The selected {$attribute} is unavailable.");
        }
    }
}
