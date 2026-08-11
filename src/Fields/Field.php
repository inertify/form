<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Traits\Tappable;
use Inertify\Form\Support\Concerns\Authorizable;
use Inertify\Form\Support\Concerns\HasResourceMetadata;
use Inertify\Form\Support\Concerns\HasVisibility;
use Inertify\Form\Support\Value;
use JsonSerializable;
use Stringable;

/** @implements Arrayable<string, mixed> */
abstract class Field implements Arrayable, JsonSerializable
{
    use Authorizable;
    use Conditionable;
    use HasResourceMetadata;
    use HasVisibility;
    use Macroable;
    use Tappable;

    protected string $name;

    protected string|Closure|null $fieldLabel;

    protected mixed $defaultValue = null;

    protected bool $hasDefaultValue = false;

    protected string|Closure|null $helpText = null;

    protected string|Closure|null $placeholderText = null;

    /** @var list<mixed> */
    protected array $validationRules = [];

    /** @var array<string, mixed> */
    protected array $managedRules = [];

    protected bool|Closure $isRequired = false;

    protected bool|Closure $isNullable = false;

    protected bool|Closure $isPrecognitive = false;

    protected bool|Closure $isDisabled = false;

    protected bool|Closure $isReadonly = false;

    protected bool|Closure $shouldAutofocus = false;

    protected bool $usesModelBinding = true;

    /** @var array<string, mixed> */
    protected array $options = [];

    final protected function __construct(string $name, ?string $label = null)
    {
        $this->name = $name;
        $this->fieldLabel = $label;
    }

    public static function make(string $name, ?string $label = null): static
    {
        return new static($name, $label);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getComponent(): string
    {
        return class_basename(static::class);
    }

    public function label(string|Closure|null $label): static
    {
        $this->fieldLabel = $label;

        return $this;
    }

    public function help(string|Closure|null $help): static
    {
        $this->helpText = $help;

        return $this;
    }

    public function placeholder(string|Closure|null $placeholder): static
    {
        $this->placeholderText = $placeholder;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->defaultValue = $value;
        $this->hasDefaultValue = true;

        return $this;
    }

    public function hasDefault(): bool
    {
        return $this->hasDefaultValue;
    }

    /** @param array<string, mixed> $data */
    public function resolveDefault(array $data = []): mixed
    {
        return Value::resolve($this->defaultValue, [
            'data' => $data,
            'field' => $this,
        ]);
    }

    public function required(bool|Closure $required = true): static
    {
        $this->isRequired = $required;

        return $this;
    }

    public function nullable(bool|Closure $nullable = true): static
    {
        $this->isNullable = $nullable;

        return $this;
    }

    public function rule(mixed $rule): static
    {
        $this->validationRules[] = $rule;

        return $this;
    }

    /** @param iterable<mixed>|mixed $rules */
    public function rules(mixed $rules): static
    {
        foreach (is_iterable($rules) ? $rules : [$rules] as $rule) {
            $this->rule($rule);
        }

        return $this;
    }

    public function precognitive(bool|Closure $precognitive = true): static
    {
        $this->isPrecognitive = $precognitive;

        return $this;
    }

    public function disabled(bool|Closure $disabled = true): static
    {
        $this->isDisabled = $disabled;

        return $this;
    }

    public function readonly(bool|Closure $readonly = true): static
    {
        $this->isReadonly = $readonly;

        return $this;
    }

    public function autofocus(bool|Closure $autofocus = true): static
    {
        $this->shouldAutofocus = $autofocus;

        return $this;
    }

    public function withoutModelBinding(bool $without = true): static
    {
        $this->usesModelBinding = ! $without;

        return $this;
    }

    public function without(bool $without = true): static
    {
        return $this->withoutModelBinding($without);
    }

    public function withModelBinding(bool $binding = true): static
    {
        $this->usesModelBinding = $binding;

        return $this;
    }

    public function usesModelBinding(): bool
    {
        return $this->usesModelBinding;
    }

    /** @param array<string, mixed> $data
     * @param  array<string, mixed>|null  $row
     */
    public function isRequiredFor(array $data = [], ?array $row = null): bool
    {
        return $this->resolveBoolean($this->isRequired, $data, $row);
    }

    public function normalizeValue(mixed $value): mixed
    {
        return $value;
    }

    public function emptyValue(): mixed
    {
        return null;
    }

    public function transformValidatedValue(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $row
     * @return list<mixed>
     */
    public function getRules(array $data = [], ?array $row = null): array
    {
        if (! $this->isAuthorized()) {
            return [];
        }

        if (! $this->isVisible($data, $row) || $this->resolveBoolean($this->isDisabled, $data, $row)) {
            return ['exclude'];
        }

        $rules = [];

        if ($this->resolveBoolean($this->isRequired, $data, $row)) {
            $rules[] = 'required';
        }

        if ($this->resolveBoolean($this->isNullable, $data, $row)) {
            $rules[] = 'nullable';
        }

        foreach ($this->validationRules as $rule) {
            foreach (is_array($rule) ? $rule : [$rule] as $item) {
                $rules[] = $item;
            }
        }

        foreach ($this->managedRules as $rule) {
            if ($rule !== null) {
                $rules[] = $rule;
            }
        }

        $seenStrings = [];

        return array_values(array_filter($rules, static function (mixed $rule) use (&$seenStrings): bool {
            if (! is_string($rule)) {
                return true;
            }

            if (isset($seenStrings[$rule])) {
                return false;
            }

            $seenStrings[$rule] = true;

            return true;
        }));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function toArrayFor(array $data = []): array
    {
        $parameters = [
            'data' => $data,
            'field' => $this,
        ];

        return [
            'component' => $this->getComponent(),
            'name' => $this->name,
            'label' => Value::resolve($this->fieldLabel, $parameters) ?? Str::headline(Arr::last(explode('.', $this->name))),
            'default' => $this->hasDefaultValue ? Value::normalize($this->resolveDefault($data)) : null,
            'help' => Value::normalize(Value::resolve($this->helpText, $parameters)),
            'placeholder' => Value::normalize(Value::resolve($this->placeholderText, $parameters)),
            'rules' => $this->serializedRules($data),
            'required' => $this->resolveBoolean($this->isRequired, $data),
            'nullable' => $this->resolveBoolean($this->isNullable, $data),
            'precognitive' => $this->resolveBoolean($this->isPrecognitive, $data),
            'disabled' => $this->resolveBoolean($this->isDisabled, $data),
            'readonly' => $this->resolveBoolean($this->isReadonly, $data),
            'autofocus' => $this->resolveBoolean($this->shouldAutofocus, $data),
            'modelBinding' => $this->usesModelBinding,
            'visibility' => $this->serializedVisibility(),
            'clearWhenHidden' => $this->shouldClearWhenHidden,
            'dataAttributes' => Value::normalize($this->resourceDataAttributes),
            'meta' => Value::normalize($this->resourceMeta),
            ...$this->serializedOptions($data),
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->toArrayFor();
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    protected function option(string $key, mixed $value): static
    {
        $this->options[$key] = $value;

        return $this;
    }

    protected function managedRule(string $key, mixed $rule): static
    {
        if ($rule === null) {
            unset($this->managedRules[$key]);
        } else {
            $this->managedRules[$key] = $rule;
        }

        return $this;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function serializedOptions(array $data): array
    {
        $parameters = ['data' => $data, 'field' => $this];

        return array_map(
            fn (mixed $value): mixed => Value::normalize(Value::resolve($value, $parameters)),
            $this->options,
        );
    }

    /** @param array<string, mixed> $data
     * @return list<mixed>
     */
    protected function serializedRules(array $data): array
    {
        return array_map(
            static fn (mixed $rule): mixed => match (true) {
                is_string($rule), is_int($rule), is_float($rule), is_bool($rule), $rule === null => $rule,
                $rule instanceof Stringable => (string) $rule,
                default => $rule::class,
            },
            $this->getRules($data),
        );
    }

    /** @param array<string, mixed> $data
     * @param  array<string, mixed>|null  $row
     */
    protected function resolveBoolean(bool|Closure $value, array $data, ?array $row = null): bool
    {
        return (bool) Value::resolve($value, [
            'data' => $data,
            'row' => $row,
            'field' => $this,
        ]);
    }

    /** @return array<string, mixed> */
    protected function evaluationParameters(): array
    {
        return ['field' => $this];
    }
}
