<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use Inertify\Form\Fields\Concerns\HasChoices;
use InvalidArgumentException;

class CheckboxGroup extends Field
{
    use HasChoices;

    protected ?int $minimumSelected = null;

    protected ?int $maximumSelected = null;

    public function minSelected(?int $minimum): static
    {
        if ($minimum !== null && ($minimum < 0 || ($this->maximumSelected !== null && $minimum > $this->maximumSelected))) {
            throw new InvalidArgumentException('Minimum selections must be non-negative and not exceed the maximum.');
        }

        $this->minimumSelected = $minimum;
        $this->managedRule('minSelected', $minimum === null ? null : 'min:'.$minimum);

        return $this->option('minSelected', $minimum);
    }

    public function maxSelected(?int $maximum): static
    {
        if ($maximum !== null && ($maximum < 0 || ($this->minimumSelected !== null && $maximum < $this->minimumSelected))) {
            throw new InvalidArgumentException('Maximum selections must be non-negative and not be less than the minimum.');
        }

        $this->maximumSelected = $maximum;
        $this->managedRule('maxSelected', $maximum === null ? null : 'max:'.$maximum);

        return $this->option('maxSelected', $maximum);
    }

    public function emptyValue(): mixed
    {
        return [];
    }

    public function normalizeValue(mixed $value): mixed
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && str_contains($value, ',')) {
            return array_map(trim(...), explode(',', $value));
        }

        return is_scalar($value) ? [$value] : [];
    }

    /** @return list<mixed> */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        return $rules === ['exclude'] ? $rules : [...$rules, 'array'];
    }
}
