<?php

declare(strict_types=1);

namespace Inertify\Form\Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

final readonly class QueryOptionValue implements ValidationRule
{
    /** @param EloquentBuilder<Model>|QueryBuilder $query */
    public function __construct(private EloquentBuilder|QueryBuilder $query, private string $column) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! (clone $this->query)->where($this->column, $value)->exists()) {
            $fail("The selected {$attribute} is invalid.");
        }
    }
}
