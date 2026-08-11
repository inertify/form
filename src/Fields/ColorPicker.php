<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use InvalidArgumentException;

class ColorPicker extends Field
{
    public function format(string $format): static
    {
        if (! in_array($format, ['hex', 'rgb', 'hsl'], true)) {
            throw new InvalidArgumentException("Unsupported color format [{$format}].");
        }

        $this->managedRule('string', 'string');
        $this->managedRule('hex', $format === 'hex' ? 'hex_color' : null);

        return $this->option('format', $format);
    }

    public function hex(): static
    {
        return $this->format('hex');
    }

    public function rgb(): static
    {
        return $this->format('rgb');
    }

    public function hsl(): static
    {
        return $this->format('hsl');
    }

    public function alpha(bool $alpha = true): static
    {
        return $this->option('alpha', $alpha);
    }

    /** @param array<string> $formats */
    public function formats(array $formats): static
    {
        $formats = array_values(array_unique($formats));

        foreach ($formats as $format) {
            if (! in_array($format, ['hex', 'rgb', 'hsl'], true)) {
                throw new InvalidArgumentException("Unsupported color format [{$format}].");
            }
        }

        return $this->option('formats', $formats);
    }

    /** @param array<int|string, string>|null $swatches */
    public function swatches(?array $swatches): static
    {
        return $this->option('swatches', $swatches);
    }

    public function clearable(bool $enabled = true): static
    {
        return $this->option('clearable', $enabled);
    }

    public function eyedropper(bool $enabled = true): static
    {
        return $this->option('eyedropper', $enabled);
    }

    public function defaultColor(?string $color): static
    {
        return $this->option('defaultColor', $color);
    }

    public function normalizeValue(mixed $value): mixed
    {
        return is_string($value) || $value === null ? $value : null;
    }

    /** @param array<string, mixed> $data */
    protected function serializedOptions(array $data): array
    {
        return ['format' => 'hex', ...parent::serializedOptions($data)];
    }

    /** @return list<mixed> */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        if ($rules === ['exclude']) {
            return $rules;
        }

        return in_array('string', $rules, true) ? $rules : [...$rules, 'string', 'hex_color'];
    }
}
