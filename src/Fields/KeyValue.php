<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use Illuminate\Contracts\Support\Arrayable;
use Inertify\Form\Support\Rules\ValidArrayKeys;
use InvalidArgumentException;
use stdClass;
use Traversable;

class KeyValue extends Field
{
    protected bool $keyedMode = true;

    /** @var list<mixed> */
    protected array $keyValidationRules = [];

    /** @var list<mixed> */
    protected array $valueValidationRules = [];

    protected ?int $minimumRows = null;

    protected ?int $maximumRows = null;

    public function keyed(bool $enabled = true): static
    {
        $this->keyedMode = $enabled;

        if (! $enabled && $this->keyValidationRules !== []) {
            throw new InvalidArgumentException('Key rules cannot be used in single mode.');
        }

        return $this->option('mode', $enabled ? 'keyed' : 'single');
    }

    public function single(bool $enabled = true): static
    {
        return $this->keyed(! $enabled);
    }

    public function mode(string $mode): static
    {
        if (! in_array($mode, ['keyed', 'single'], true)) {
            throw new InvalidArgumentException("Unsupported key-value mode [{$mode}].");
        }

        return $mode === 'keyed' ? $this->keyed() : $this->single();
    }

    public function keyRule(mixed $rule): static
    {
        if (! $this->keyedMode) {
            throw new InvalidArgumentException('Key rules cannot be used in single mode.');
        }

        $this->keyValidationRules[] = $rule;

        return $this;
    }

    /** @param iterable<mixed>|mixed $rules */
    public function keyRules(mixed $rules): static
    {
        foreach (is_iterable($rules) ? $rules : [$rules] as $rule) {
            $this->keyRule($rule);
        }

        return $this;
    }

    public function valueRule(mixed $rule): static
    {
        $this->valueValidationRules[] = $rule;

        return $this;
    }

    /** @param iterable<mixed>|mixed $rules */
    public function valueRules(mixed $rules): static
    {
        foreach (is_iterable($rules) ? $rules : [$rules] as $rule) {
            $this->valueRule($rule);
        }

        return $this;
    }

    /** @return list<mixed> */
    public function getKeyRules(): array
    {
        return $this->keyValidationRules;
    }

    /** @return list<mixed> */
    public function getValueRules(): array
    {
        return $this->valueValidationRules;
    }

    public function keyLabel(?string $label): static
    {
        return $this->option('keyLabel', $label);
    }

    public function valueLabel(?string $label): static
    {
        return $this->option('valueLabel', $label);
    }

    public function addLabel(?string $label): static
    {
        return $this->option('addLabel', $label);
    }

    public function minItems(?int $minimum): static
    {
        if ($minimum !== null && ($minimum < 0 || ($this->maximumRows !== null && $minimum > $this->maximumRows))) {
            throw new InvalidArgumentException('Key-value minimum rows must not exceed its non-negative maximum.');
        }

        $this->minimumRows = $minimum;
        $this->managedRule('minRows', $minimum === null ? null : 'min:'.$minimum);

        return $this->option('minItems', $minimum);
    }

    public function maxItems(?int $maximum): static
    {
        if ($maximum !== null && ($maximum < 0 || ($this->minimumRows !== null && $maximum < $this->minimumRows))) {
            throw new InvalidArgumentException('Key-value maximum rows must not be less than its non-negative minimum.');
        }

        $this->maximumRows = $maximum;
        $this->managedRule('maxRows', $maximum === null ? null : 'max:'.$maximum);

        return $this->option('maxItems', $maximum);
    }

    public function reorderable(bool $reorderable = true): static
    {
        return $this->option('reorderable', $reorderable);
    }

    public function emptyValue(): mixed
    {
        return $this->keyedMode ? new stdClass : [];
    }

    public function normalizeValue(mixed $value): mixed
    {
        if ($value === null || $value instanceof stdClass) {
            return $this->emptyValue();
        }

        if ($value instanceof Traversable) {
            $value = iterator_to_array($value);
        } elseif ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if (! is_array($value)) {
            return $this->emptyValue();
        }

        return $this->keyedMode ? $value : array_values($value);
    }

    /** @param array<string, mixed> $data */
    protected function serializedOptions(array $data): array
    {
        return [
            ...parent::serializedOptions($data),
            'mode' => $this->keyedMode ? 'keyed' : 'single',
            'keyRules' => $this->keyValidationRules,
            'valueRules' => $this->valueValidationRules,
        ];
    }

    /** @return list<mixed> */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        if ($rules === ['exclude']) {
            return $rules;
        }

        $rules[] = 'array';

        if ($this->keyedMode && $this->keyValidationRules !== []) {
            $rules[] = new ValidArrayKeys($this->keyValidationRules);
        }

        return $rules;
    }

    public function minRows(?int $minimum): static
    {
        return $this->minItems($minimum)->option('minRows', $minimum);
    }

    public function maxRows(?int $maximum): static
    {
        return $this->maxItems($maximum)->option('maxRows', $maximum);
    }

    public function keyHeader(?string $label): static
    {
        return $this->keyLabel($label)->option('keyHeader', $label);
    }

    public function valueHeader(?string $label): static
    {
        return $this->valueLabel($label)->option('valueHeader', $label);
    }

    public function addButtonLabel(?string $label): static
    {
        return $this->addLabel($label)->option('addButtonLabel', $label);
    }
}
