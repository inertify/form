<?php

declare(strict_types=1);

namespace Inertify\Form\RichText;

use Illuminate\Support\Arr;

final readonly class RichTextStoredImage
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, string>  $attributes
     */
    public function __construct(
        private string $identifier,
        private array $metadata,
        private array $attributes,
    ) {}

    public function identifier(): string
    {
        return $this->identifier;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function meta(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->metadata, $key, $default);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return $this->attributes;
    }
}
