<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use Inertify\Form\Fields\Concerns\HasChoices;

class Radio extends Field
{
    use HasChoices;

    /** @return list<mixed> */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        return $rules === ['exclude'] ? $rules : [...$rules, ...$this->getChoiceRules($data)];
    }
}
