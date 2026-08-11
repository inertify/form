<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use Closure;
use Inertify\Form\Support\Value;
use InvalidArgumentException;

class Repeater extends Field
{
    /** @var list<Field|Fieldset>|Closure */
    protected array|Closure $repeaterFields = [];

    /** @var list<Field|Fieldset>|null */
    protected ?array $resolvedRepeaterFields = null;

    /** @var array<string, mixed>|Closure|null */
    protected array|Closure|null $newItemDefaults = null;

    protected ?int $minimumItems = null;

    protected ?int $maximumItems = null;

    /** @param array<Field|Fieldset>|Closure $fields */
    public function schema(array|Closure $fields): static
    {
        $this->repeaterFields = $fields instanceof Closure ? $fields : array_values($fields);
        $this->resolvedRepeaterFields = null;
        $this->managedRule('array', 'array');

        return $this;
    }

    /** @param array<Field|Fieldset>|Closure $fields */
    public function fields(array|Closure $fields): static
    {
        return $this->schema($fields);
    }

    /** @return list<Field|Fieldset> */
    public function getFields(): array
    {
        if ($this->resolvedRepeaterFields !== null) {
            return $this->resolvedRepeaterFields;
        }

        $fields = Value::resolve($this->repeaterFields, ['field' => $this]);

        if (! is_array($fields)) {
            throw new InvalidArgumentException('Repeater schema must resolve to an array.');
        }

        return $this->resolvedRepeaterFields = array_values($fields);
    }

    public function minItems(?int $minimum): static
    {
        if ($minimum !== null && ($minimum < 0 || ($this->maximumItems !== null && $minimum > $this->maximumItems))) {
            throw new InvalidArgumentException('Repeater minimum must be non-negative and not exceed its maximum.');
        }

        $this->minimumItems = $minimum;
        $this->managedRule('minItems', $minimum === null ? null : 'min:'.$minimum);

        return $this->option('minItems', $minimum);
    }

    public function maxItems(?int $maximum): static
    {
        if ($maximum !== null && ($maximum < 0 || ($this->minimumItems !== null && $maximum < $this->minimumItems))) {
            throw new InvalidArgumentException('Repeater maximum must be non-negative and not be less than its minimum.');
        }

        $this->maximumItems = $maximum;
        $this->managedRule('maxItems', $maximum === null ? null : 'max:'.$maximum);

        return $this->option('maxItems', $maximum);
    }

    public function addable(bool $addable = true): static
    {
        return $this->option('addable', $addable);
    }

    public function deletable(bool $deletable = true): static
    {
        return $this->option('deletable', $deletable);
    }

    public function reorderable(bool $reorderable = true): static
    {
        return $this->option('reorderable', $reorderable);
    }

    /** @param array<string, mixed>|Closure $defaults */
    public function defaultItem(array|Closure $defaults): static
    {
        $this->newItemDefaults = $defaults;

        return $this;
    }

    public function itemLabel(string|Closure|null $label): static
    {
        return $this->option('itemLabel', $label);
    }

    public function addButtonText(?string $text): static
    {
        return $this->option('addButtonText', $text);
    }

    /** @param array<string, mixed> $data */
    protected function serializedOptions(array $data): array
    {
        $fields = $this->getFields();

        return [
            ...parent::serializedOptions($data),
            'schema' => array_values(array_map(
                fn (Field|Fieldset $field): array => $field->toArrayFor($data),
                array_filter($fields, fn (Field|Fieldset $field): bool => $field->isAuthorized()),
            )),
            'defaultItem' => $this->resolveDefaultItem($fields, $data),
        ];
    }

    public function emptyValue(): mixed
    {
        return [];
    }

    /**
     * @param  list<Field|Fieldset>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function resolveDefaultItem(array $fields, array $data): array
    {
        $defaults = [];

        foreach ($fields as $field) {
            if (! $field->isAuthorized()) {
                continue;
            }

            if ($field instanceof Fieldset) {
                $defaults = array_replace_recursive($defaults, $this->resolveDefaultItem($field->getFields(), $data));

                continue;
            }

            if (! str_starts_with($field->getName(), '$.')) {
                data_set($defaults, $field->getName(), $field->hasDefault() ? $field->resolveDefault($data) : null);
            }
        }

        $configured = Value::resolve($this->newItemDefaults, ['data' => $data, 'field' => $this]);

        return is_array($configured) ? array_replace_recursive($defaults, $configured) : $defaults;
    }
}
