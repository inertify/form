<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use Illuminate\Validation\Rule;

class Checkbox extends Field
{
    public function trueValue(mixed $value): static
    {
        return $this->option('trueValue', $value);
    }

    public function falseValue(mixed $value): static
    {
        return $this->option('falseValue', $value);
    }

    public function indeterminate(bool $indeterminate = true): static
    {
        return $this->option('indeterminate', $indeterminate);
    }

    public function normalizeValue(mixed $value): mixed
    {
        $true = $this->options['trueValue'] ?? true;
        $false = $this->options['falseValue'] ?? false;

        if ($value === $true || $value === $false) {
            return $value;
        }

        return $value ? $true : $false;
    }

    public function emptyValue(): mixed
    {
        return $this->options['falseValue'] ?? false;
    }

    /** @return list<mixed> */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        return $rules === ['exclude'] ? $rules : [
            ...$rules,
            Rule::in([$this->options['trueValue'] ?? true, $this->options['falseValue'] ?? false]),
        ];
    }
}
