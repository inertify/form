<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Inertify\Form\Support\Concerns\Authorizable;
use Inertify\Form\Support\Concerns\HasResourceMetadata;
use Inertify\Form\Support\Value;
use InvalidArgumentException;
use JsonSerializable;

/** @implements Arrayable<string, mixed> */
class BlockSet implements Arrayable, JsonSerializable
{
    use Authorizable;
    use HasResourceMetadata;

    /** @var list<Field|Fieldset> */
    protected array $blockFields = [];

    protected string|Closure|null $blockLabel;

    protected string|Closure|null $blockDescription = null;

    /** @var array<string, mixed>|Closure|null */
    protected array|Closure|null $blockDefaults = null;

    protected ?int $maximumItems = null;

    final protected function __construct(protected string $blockName, string|Closure|null $label = null)
    {
        $this->blockName = trim($this->blockName);

        if ($this->blockName === '') {
            throw new InvalidArgumentException('Block set types must not be empty.');
        }

        $this->blockLabel = $label;
    }

    public static function make(string $name, string|Closure|null $label = null): static
    {
        return new static($name, $label);
    }

    /** @param array<Field|Fieldset> $fields */
    public function fields(array $fields): static
    {
        $this->blockFields = array_values($fields);

        return $this;
    }

    /** @param array<Field|Fieldset> $fields */
    public function schema(array $fields): static
    {
        return $this->fields($fields);
    }

    /** @return list<Field|Fieldset> */
    public function getFields(): array
    {
        return $this->blockFields;
    }

    public function getName(): string
    {
        return $this->blockName;
    }

    public function label(string|Closure|null $label): static
    {
        $this->blockLabel = $label;

        return $this;
    }

    public function description(string|Closure|null $description): static
    {
        $this->blockDescription = $description;

        return $this;
    }

    /** @param array<string, mixed>|Closure $defaults */
    public function default(array|Closure $defaults): static
    {
        $this->blockDefaults = $defaults;

        return $this;
    }

    public function maxItems(?int $maximum): static
    {
        if ($maximum !== null && $maximum < 0) {
            throw new InvalidArgumentException('Block set maximum must be zero or greater.');
        }

        $this->maximumItems = $maximum;

        return $this;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function toArrayFor(array $data = []): array
    {
        return [
            'type' => $this->blockName,
            'label' => Value::normalize(Value::resolve($this->blockLabel, [
                'data' => $data,
                'block' => $this,
            ])) ?? str($this->blockName)->headline()->toString(),
            'description' => Value::normalize(Value::resolve($this->blockDescription, [
                'data' => $data,
                'block' => $this,
            ])),
            'maxItems' => $this->maximumItems,
            'defaultData' => $this->resolveDefaultData($data),
            'schema' => array_values(array_map(
                fn (Field|Fieldset $field): array => $field->toArrayFor($data),
                array_filter($this->blockFields, fn (Field|Fieldset $field): bool => $field->isAuthorized()),
            )),
            'dataAttributes' => Value::normalize($this->resourceDataAttributes),
            'meta' => Value::normalize($this->resourceMeta),
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

    /** @return array<string, mixed> */
    protected function evaluationParameters(): array
    {
        return ['block' => $this];
    }

    public function getMaxItems(): ?int
    {
        return $this->maximumItems;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function resolveDefaultData(array $data = []): array
    {
        $defaults = $this->fieldDefaults($this->blockFields, $data);
        $configured = Value::resolve($this->blockDefaults, [
            'data' => $data,
            'block' => $this,
        ]);

        return is_array($configured) ? array_replace_recursive($defaults, $configured) : $defaults;
    }

    /** @param list<Field|Fieldset> $fields
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fieldDefaults(array $fields, array $data): array
    {
        $defaults = [];

        foreach ($fields as $field) {
            if (! $field->isAuthorized()) {
                continue;
            }

            if ($field instanceof Fieldset) {
                $defaults = array_replace_recursive($defaults, $this->fieldDefaults($field->getFields(), $data));
            } elseif (! str_starts_with($field->getName(), '$.')) {
                data_set($defaults, $field->getName(), $field->hasDefault() ? $field->resolveDefault($data) : null);
            }
        }

        return $defaults;
    }
}
