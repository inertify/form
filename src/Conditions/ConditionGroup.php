<?php

declare(strict_types=1);

namespace Inertify\Form\Conditions;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Inertify\Form\Support\Value;
use JsonSerializable;

/** @implements Arrayable<string, mixed> */
final class ConditionGroup implements Arrayable, JsonSerializable
{
    /**
     * @param  list<Condition|ConditionGroup|bool|Closure>  $conditions
     */
    public function __construct(
        public readonly string $type,
        public readonly array $conditions,
    ) {}

    /** @param list<Condition|ConditionGroup|bool|Closure> $conditions */
    public static function all(array $conditions): self
    {
        return new self('and', $conditions);
    }

    /** @param list<Condition|ConditionGroup|bool|Closure> $conditions */
    public static function any(array $conditions): self
    {
        return new self('or', $conditions);
    }

    public static function not(Condition|ConditionGroup|bool|Closure $condition): self
    {
        return new self('not', [$condition]);
    }

    /**
     * @param  array<string, mixed>  $root
     * @param  array<string, mixed>|null  $row
     */
    public function matches(array $root, ?array $row = null): bool
    {
        $matches = fn (Condition|ConditionGroup|bool|Closure $condition): bool => match (true) {
            $condition instanceof Condition => $condition->matches($root, $row),
            $condition instanceof self => $condition->matches($root, $row),
            $condition instanceof Closure => (bool) Value::resolve($condition, [
                'data' => $root,
                'row' => $row,
            ]),
            default => $condition,
        };

        return match ($this->type) {
            'or' => collect($this->conditions)->contains($matches),
            'not' => ! $matches($this->conditions[0]),
            default => collect($this->conditions)->every($matches),
        };
    }

    /** @return array{mode: string, conditions: list<mixed>} */
    public function toArray(): array
    {
        return [
            'mode' => $this->type,
            'conditions' => array_map(
                fn (Condition|ConditionGroup|bool|Closure $condition): mixed => $condition instanceof Closure
                    ? (bool) Value::resolve($condition)
                    : Value::normalize($condition),
                $this->conditions,
            ),
            'dependsOn' => $this->dependencies(),
        ];
    }

    /** @return array{mode: string, conditions: list<mixed>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        $dependencies = [];

        foreach ($this->conditions as $condition) {
            if ($condition instanceof Condition || $condition instanceof self) {
                $dependencies = [...$dependencies, ...$condition->dependencies()];
            }
        }

        return array_values(array_unique($dependencies));
    }
}
