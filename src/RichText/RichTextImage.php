<?php

declare(strict_types=1);

namespace Inertify\Form\RichText;

use InvalidArgumentException;

final class RichTextImage
{
    /** @param array<string, string> $attributes */
    private function __construct(private array $attributes = []) {}

    /** @param array<string, mixed> $attributes */
    public static function fromAttributes(array $attributes = []): self
    {
        $image = new self;

        return $image->attributes($attributes);
    }

    public function src(?string $src): self
    {
        return $this->attribute('src', $src);
    }

    public function alt(?string $alt): self
    {
        return $this->attribute('alt', $alt);
    }

    public function title(?string $title): self
    {
        return $this->attribute('title', $title);
    }

    public function width(int|string|null $width): self
    {
        return $this->attribute('width', $width);
    }

    public function height(int|string|null $height): self
    {
        return $this->attribute('height', $height);
    }

    public function dimensions(int|string|null $width, int|string|null $height): self
    {
        return $this->width($width)->height($height);
    }

    /** @param array<string, mixed> $metadata */
    public function identifier(string|int $identifier, array $metadata = []): self
    {
        return $this->attribute(
            RichTextMarker::STORED_ATTRIBUTE,
            RichTextMarker::encode((string) $identifier, $metadata),
        );
    }

    public function attribute(string $name, mixed $value): self
    {
        if (! preg_match('/^[A-Za-z_:][A-Za-z0-9_.:-]*$/', $name)) {
            throw new InvalidArgumentException("Invalid image attribute [{$name}].");
        }

        if ($value === null || $value === false) {
            unset($this->attributes[$name]);

            return $this;
        }

        if (! is_scalar($value)) {
            throw new InvalidArgumentException("Image attribute [{$name}] must be scalar or null.");
        }

        $this->attributes[$name] = $value === true ? $name : (string) $value;

        return $this;
    }

    /** @param array<string, mixed> $attributes */
    public function attributes(array $attributes): self
    {
        foreach ($attributes as $name => $value) {
            $this->attribute((string) $name, $value);
        }

        return $this;
    }

    /** @return array<string, string> */
    public function toAttributes(): array
    {
        return $this->attributes;
    }
}
