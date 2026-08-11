<?php

declare(strict_types=1);

namespace Inertify\Form\Support\Concerns;

use Illuminate\Support\Str;

trait HasResourceMetadata
{
    /** @var array<string, mixed>|null */
    protected ?array $resourceDataAttributes = null;

    /** @var array<string, mixed>|null */
    protected ?array $resourceMeta = null;

    public function dataAttribute(string $key, mixed $value): static
    {
        $key = Str::startsWith($key, 'data-') ? $key : 'data-'.Str::kebab($key);
        $this->resourceDataAttributes ??= [];
        $this->resourceDataAttributes[$key] = $value;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function dataAttributes(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->dataAttribute((string) $key, $value);
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>|string  $key
     */
    public function meta(array|string $key, mixed $value = null): static
    {
        $this->resourceMeta ??= [];

        if (is_array($key)) {
            $this->resourceMeta = $key;
        } else {
            $this->resourceMeta[$key] = $value;
        }

        return $this;
    }
}
