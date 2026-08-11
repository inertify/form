<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

class Slug extends TextInput
{
    /** @param array<string, mixed> $data */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        return in_array('exclude', $rules, true)
            ? ['exclude']
            : array_values(array_unique(['string', ...$rules], SORT_REGULAR));
    }

    public function from(string $field): static
    {
        return $this->option('from', $field);
    }

    public function separator(string $separator): static
    {
        return $this->option('separator', $separator);
    }

    public function lockOnManualEdit(bool $enabled = true): static
    {
        return $this->option('lockOnManualEdit', $enabled);
    }

    public function onlyWhenEmpty(bool $enabled = true): static
    {
        return $this->option('onlyWhenEmpty', $enabled);
    }

    public function updateOnEdit(bool $enabled = true): static
    {
        return $this->option('updateOnEdit', $enabled);
    }

    public function lowercase(bool $enabled = true): static
    {
        return $this->option('lowercase', $enabled);
    }
}
