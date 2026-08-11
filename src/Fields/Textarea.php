<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

class Textarea extends Field
{
    /** @return list<mixed> */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        return $rules === ['exclude'] ? $rules : [...$rules, 'string'];
    }

    public function minLength(?int $length): static
    {
        $this->managedRule('minLength', $length === null ? null : 'min:'.$length);

        return $this->option('minLength', $length);
    }

    public function maxLength(?int $length): static
    {
        $this->managedRule('maxLength', $length === null ? null : 'max:'.$length);

        return $this->option('maxLength', $length);
    }
}
