<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Tappable;
use Inertify\Form\Support\Concerns\Authorizable;
use Inertify\Form\Support\Concerns\HasResourceMetadata;
use Inertify\Form\Support\Concerns\HasVisibility;
use Inertify\Form\Support\Value;
use JsonSerializable;

/** @implements Arrayable<string, mixed> */
class Fieldset implements Arrayable, JsonSerializable
{
    use Authorizable;
    use Conditionable;
    use HasResourceMetadata;
    use HasVisibility;
    use Tappable;

    /** @var list<Field|Fieldset> */
    protected array $fieldsetFields = [];

    protected string|Closure|null $fieldsetLegend = null;

    protected string|Closure|null $fieldsetDescription = null;

    protected ?string $fieldsetId = null;

    /** @param array<Field|Fieldset>|string|null $fieldsOrLegend */
    final protected function __construct(array|string|null $fieldsOrLegend = null)
    {
        if (is_array($fieldsOrLegend)) {
            $this->fields($fieldsOrLegend);
        } elseif (is_string($fieldsOrLegend)) {
            $this->legend($fieldsOrLegend);
        }
    }

    /** @param array<Field|Fieldset>|string|null $fieldsOrLegend */
    public static function make(array|string|null $fieldsOrLegend = null): static
    {
        return new static($fieldsOrLegend);
    }

    /** @param array<Field|Fieldset> $fields */
    public function fields(array $fields): static
    {
        $this->fieldsetFields = array_values($fields);

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
        return $this->fieldsetFields;
    }

    public function legend(string|Closure|null $legend): static
    {
        $this->fieldsetLegend = $legend;

        return $this;
    }

    public function description(string|Closure|null $description): static
    {
        $this->fieldsetDescription = $description;

        return $this;
    }

    public function id(?string $id): static
    {
        $this->fieldsetId = $id;

        return $this;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function toArrayFor(array $data = []): array
    {
        $parameters = ['data' => $data, 'fieldset' => $this];

        return [
            'id' => $this->fieldsetId,
            'legend' => Value::normalize(Value::resolve($this->fieldsetLegend, $parameters)),
            'description' => Value::normalize(Value::resolve($this->fieldsetDescription, $parameters)),
            'fields' => array_values(array_map(
                fn (Field|self $field): array => $field->toArrayFor($data),
                array_filter($this->fieldsetFields, fn (Field|self $field): bool => $field->isAuthorized()),
            )),
            'visibility' => $this->serializedVisibility(),
            'clearWhenHidden' => $this->shouldClearWhenHidden,
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
        return ['fieldset' => $this];
    }
}
