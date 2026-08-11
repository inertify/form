<?php

declare(strict_types=1);

namespace Inertify\Form\Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ValidArrayKeys implements ValidationRule
{
    /** @param list<mixed> $rules */
    public function __construct(protected array $rules) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach (array_keys($value) as $key) {
            $validator = validator(['key' => (string) $key], ['key' => $this->rules]);

            if ($validator->fails()) {
                $fail($validator->errors()->first('key'));
            }
        }
    }
}
