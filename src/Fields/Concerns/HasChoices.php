<?php

declare(strict_types=1);

namespace Inertify\Form\Fields\Concerns;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Validation\Rule;
use Inertify\Form\Support\Value;
use Traversable;

trait HasChoices
{
    protected mixed $choiceOptions = [];

    protected string|Closure $choiceLabel = 'label';

    protected string|Closure $choiceValue = 'value';

    protected string|Closure|null $choiceDescription = 'description';

    protected string|Closure|null $choiceDisabled = 'disabled';

    protected string|Closure|null $choiceDisabledReason = 'disabledReason';

    /** @var array<int|string, mixed>|Closure|null */
    protected array|Closure|null $choiceLabelMap = null;

    /** @var array<int|string, mixed>|Closure|null */
    protected array|Closure|null $choiceValueMap = null;

    /** @var array<int|string, mixed>|Closure|null */
    protected array|Closure|null $choiceDescriptionMap = null;

    public function options(
        mixed $options,
        string|Closure $label = 'label',
        string|Closure $value = 'value',
        string|Closure|null $description = 'description',
    ): static {
        $this->choiceOptions = $options;
        $this->choiceLabel = $label;
        $this->choiceValue = $value;
        $this->choiceDescription = $description;

        return $this;
    }

    public function optionLabel(string|Closure $mapping): static
    {
        $this->choiceLabel = $mapping;

        return $this;
    }

    public function optionValue(string|Closure $mapping): static
    {
        $this->choiceValue = $mapping;

        return $this;
    }

    public function optionDescription(string|Closure|null $mapping): static
    {
        $this->choiceDescription = $mapping;

        return $this;
    }

    public function optionDisabled(string|Closure|null $mapping): static
    {
        $this->choiceDisabled = $mapping;

        return $this;
    }

    public function optionDisabledReason(string|Closure|null $mapping): static
    {
        $this->choiceDisabledReason = $mapping;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $mapping */
    public function mapAs(array|Closure $mapping): static
    {
        $this->choiceLabelMap = $mapping;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $mapping */
    public function mapValueAs(array|Closure $mapping): static
    {
        $this->choiceValueMap = $mapping;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $mapping */
    public function mapDescriptionAs(array|Closure $mapping): static
    {
        $this->choiceDescriptionMap = $mapping;

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        return $this->option('multiple', $multiple);
    }

    public function searchable(bool $searchable = true): static
    {
        return $this->option('searchable', $searchable);
    }

    public function clearable(bool $clearable = true): static
    {
        return $this->option('clearable', $clearable);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<mixed>
     */
    public function getChoiceRules(array $data = []): array
    {
        $options = $this->normalizedChoices($data);

        return $options === [] ? [] : [Rule::in(array_column($options, 'value'))];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function serializedOptions(array $data): array
    {
        return [
            ...parent::serializedOptions($data),
            'options' => $this->normalizedChoices($data),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    protected function normalizedChoices(array $data): array
    {
        $options = Value::resolve($this->choiceOptions, ['data' => $data, 'field' => $this]);

        if (is_string($options) && is_subclass_of($options, Model::class)) {
            $options = $options::query();
        }

        if ($options instanceof EloquentBuilder) {
            $query = clone $options;
            $relations = [];

            foreach ([$this->choiceLabel, $this->choiceValue, $this->choiceDescription] as $mapping) {
                if (is_string($mapping) && str_contains($mapping, '.')) {
                    $relations[] = str($mapping)->beforeLast('.')->toString();
                }
            }

            if ($relations !== []) {
                $query->with(array_values(array_unique($relations)));
            }

            $options = $query->get()->all();
        } elseif ($options instanceof QueryBuilder) {
            $options = (clone $options)->get()->all();
        } elseif ($options instanceof Traversable) {
            $options = iterator_to_array($options);
        } elseif ($options instanceof Arrayable) {
            $options = $options->toArray();
        }

        if (! is_array($options)) {
            return [];
        }

        $normalized = [];
        $isList = array_is_list($options);

        foreach ($options as $key => $record) {
            $isScalar = is_scalar($record) || $record === null;
            $parameters = ['record' => $record, 'option' => $record, 'key' => $key, 'field' => $this];

            if (is_object($record)) {
                $parameters[$record::class] = $record;
            }

            $read = static fn (string|Closure|null $mapping): mixed => match (true) {
                $mapping instanceof Closure => Value::resolve($mapping, $parameters),
                $mapping === null => null,
                default => data_get($record, $mapping),
            };

            $label = $isScalar ? $record : $read($this->choiceLabel);
            $value = $isScalar ? ($isList ? $record : $key) : $read($this->choiceValue);
            $description = $isScalar ? null : $read($this->choiceDescription);

            $normalized[] = [
                'label' => Value::normalize($this->remapChoice($label, $this->choiceLabelMap, $parameters)),
                'value' => Value::normalize($this->remapChoice($value, $this->choiceValueMap, $parameters)),
                'description' => Value::normalize($this->remapChoice($description, $this->choiceDescriptionMap, $parameters)),
                'disabled' => (bool) ($isScalar ? false : $read($this->choiceDisabled)),
                'disabledReason' => Value::normalize($isScalar ? null : $read($this->choiceDisabledReason)),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int|string, mixed>|Closure|null  $mapping
     * @param  array<string, mixed>  $parameters
     */
    private function remapChoice(mixed $value, array|Closure|null $mapping, array $parameters): mixed
    {
        if ($mapping instanceof Closure) {
            return Value::resolve($mapping, [...$parameters, 'value' => $value]);
        }

        return is_array($mapping) && (is_int($value) || is_string($value))
            ? ($mapping[$value] ?? $value)
            : $value;
    }
}
