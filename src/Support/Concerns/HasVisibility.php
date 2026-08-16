<?php

declare(strict_types=1);

namespace Inertify\Form\Support\Concerns;

use Closure;
use Inertify\Form\Conditions\Condition;
use Inertify\Form\Conditions\ConditionGroup;
use Inertify\Form\Conditions\Visibility;
use Inertify\Form\Support\Value;

trait HasVisibility
{
    protected Condition|ConditionGroup|bool|Closure|null $visibility = null;

    protected bool $shouldClearWhenHidden = false;

    public function visible(bool|Closure $visible = true): static
    {
        $this->visibility = $visible;

        return $this;
    }

    public function hidden(bool|Closure $hidden = true): static
    {
        $this->visibility = $hidden instanceof Closure
            ? fn (array $data = [], ?array $row = null): bool => ! (bool) Value::resolve($hidden, compact('data', 'row'))
            : ! $hidden;

        return $this;
    }

    public function visibleWhen(string $name, mixed $operator = '=', mixed $value = null): static
    {
        return $this->addVisibility(Condition::make($name, $operator, $value));
    }

    public function hiddenWhen(string $name, mixed $operator = '=', mixed $value = null): static
    {
        return $this->addVisibility(ConditionGroup::not(Condition::make($name, $operator, $value)));
    }

    /** @param array<mixed> $values */
    public function visibleWhenIn(string $name, array $values): static
    {
        return $this->visibleWhen($name, 'in', $values);
    }

    /** @param array<mixed> $values */
    public function hiddenWhenIn(string $name, array $values): static
    {
        return $this->hiddenWhen($name, 'in', $values);
    }

    /** @param array<mixed>|Closure $conditions */
    public function visibleWhenAll(array|Closure $conditions): static
    {
        return $this->addVisibility(ConditionGroup::all($this->resolveConditions($conditions)));
    }

    /** @param array<mixed>|Closure $conditions */
    public function visibleWhenAny(array|Closure $conditions): static
    {
        return $this->addVisibility(ConditionGroup::any($this->resolveConditions($conditions)));
    }

    /** @param Condition|ConditionGroup|array<mixed>|Closure $condition */
    public function visibleWhenNot(Condition|ConditionGroup|array|Closure $condition): static
    {
        if ($condition instanceof Closure) {
            return $this->addVisibility(ConditionGroup::not(ConditionGroup::any($this->resolveConditions($condition))));
        }

        return $this->addVisibility(ConditionGroup::not($this->normalizeCondition($condition)));
    }

    public function clearWhenHidden(bool $clear = true): static
    {
        $this->shouldClearWhenHidden = $clear;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $row
     */
    public function isVisible(array $data = [], ?array $row = null): bool
    {
        return match (true) {
            $this->visibility instanceof Condition => $this->visibility->matches($data, $row),
            $this->visibility instanceof ConditionGroup => $this->visibility->matches($data, $row),
            $this->visibility instanceof Closure => (bool) Value::resolve($this->visibility, compact('data', 'row')),
            $this->visibility === null => true,
            default => (bool) $this->visibility,
        };
    }

    public function clearsWhenHidden(): bool
    {
        return $this->shouldClearWhenHidden;
    }

    protected function serializedVisibility(): mixed
    {
        return match (true) {
            $this->visibility instanceof Closure => (bool) Value::resolve($this->visibility),
            default => Value::normalize($this->visibility),
        };
    }

    protected function addVisibility(Condition|ConditionGroup $condition): static
    {
        $this->visibility = $this->visibility === null
            ? $condition
            : ConditionGroup::all([$this->visibility, $condition]);

        return $this;
    }

    /** @param array<mixed> $conditions
     * @return list<Condition|ConditionGroup>
     */
    private function normalizeConditions(array $conditions): array
    {
        if (array_is_list($conditions)) {
            return array_map($this->normalizeCondition(...), $conditions);
        }

        $normalized = [];
        foreach ($conditions as $name => $value) {
            $normalized[] = Condition::make((string) $name, '=', $value);
        }

        return $normalized;
    }

    /** @param Condition|ConditionGroup|array<mixed> $condition */
    private function normalizeCondition(Condition|ConditionGroup|array $condition): Condition|ConditionGroup
    {
        if ($condition instanceof Condition || $condition instanceof ConditionGroup) {
            return $condition;
        }

        if (! array_is_list($condition)) {
            $conditions = $this->normalizeConditions($condition);

            return count($conditions) === 1 ? $conditions[0] : ConditionGroup::all($conditions);
        }

        return Condition::make(
            (string) ($condition[0] ?? ''),
            $condition[1] ?? '=',
            $condition[2] ?? null,
        );
    }

    /** @param array<mixed>|Closure $conditions
     * @return list<Condition|ConditionGroup>
     */
    private function resolveConditions(array|Closure $conditions): array
    {
        if (is_array($conditions)) {
            return $this->normalizeConditions($conditions);
        }

        $builder = new Visibility;
        $result = $conditions($builder);

        return $result instanceof Visibility ? $result->conditions() : $builder->conditions();
    }
}
