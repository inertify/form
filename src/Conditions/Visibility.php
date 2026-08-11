<?php

declare(strict_types=1);

namespace Inertify\Form\Conditions;

final class Visibility
{
    /** @var list<Condition> */
    private array $conditions = [];

    public function where(string $field, mixed $operator = '=', mixed $value = null): self
    {
        $this->conditions[] = Condition::make($field, $operator, $value);

        return $this;
    }

    /** @return list<Condition> */
    public function conditions(): array
    {
        return $this->conditions;
    }
}
