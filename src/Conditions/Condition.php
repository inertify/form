<?php

declare(strict_types=1);

namespace Inertify\Form\Conditions;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Inertify\Form\Support\Value;
use JsonSerializable;

/** @implements Arrayable<string, mixed> */
final class Condition implements Arrayable, JsonSerializable
{
    /** @var list<string> */
    public const array OPERATORS = [
        '=', '!=', '<', '<=', '>', '>=', 'in', 'not_in', 'contains',
        'empty', 'not_empty', 'truthy', 'falsy',
    ];

    public function __construct(
        public readonly string $name,
        public readonly string $operator = '=',
        public readonly mixed $value = null,
    ) {}

    public static function make(string $name, mixed $operator = '=', mixed $value = null): self
    {
        if (! is_string($operator) || ! in_array($operator, self::OPERATORS, true)) {
            $value = $operator;
            $operator = '=';
        }

        return new self($name, $operator, $value);
    }

    /**
     * @param  array<string, mixed>  $root
     * @param  array<string, mixed>|null  $row
     */
    public function matches(array $root, ?array $row = null): bool
    {
        $path = $this->name;
        $source = $root;

        if (str_starts_with($path, '$.')) {
            $path = substr($path, 2);
        } elseif ($row !== null && Arr::has($row, $path)) {
            $source = $row;
        }

        $actual = data_get($source, $path);

        return match ($this->operator) {
            '=' => $this->equal($actual, $this->value),
            '!=' => ! $this->equal($actual, $this->value),
            '<' => $this->numericCompare($actual, $this->value, fn (float $left, float $right): bool => $left < $right),
            '<=' => $this->numericCompare($actual, $this->value, fn (float $left, float $right): bool => $left <= $right),
            '>' => $this->numericCompare($actual, $this->value, fn (float $left, float $right): bool => $left > $right),
            '>=' => $this->numericCompare($actual, $this->value, fn (float $left, float $right): bool => $left >= $right),
            'in' => $this->containsEquivalent(Arr::wrap($this->value), $actual),
            'not_in' => ! $this->containsEquivalent(Arr::wrap($this->value), $actual),
            'contains' => is_array($actual)
                ? $this->containsEquivalent($actual, $this->value)
                : str_contains((string) $actual, (string) $this->value),
            'empty' => blank($actual),
            'not_empty' => filled($actual),
            'truthy' => (bool) $actual,
            'falsy' => ! (bool) $actual,
            default => false,
        };
    }

    /** @return array{field: string, operator: string, value?: mixed, dependsOn: list<string>} */
    public function toArray(): array
    {
        $condition = [
            'field' => $this->name,
            'operator' => $this->operator,
        ];

        if (! in_array($this->operator, ['empty', 'not_empty', 'truthy', 'falsy'], true)) {
            $condition['value'] = Value::normalize($this->value);
        }

        $condition['dependsOn'] = $this->dependencies();

        return $condition;
    }

    /** @return array{field: string, operator: string, value?: mixed, dependsOn: list<string>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return [$this->name];
    }

    private function equal(mixed $left, mixed $right): bool
    {
        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left === (float) $right;
        }

        return $left === $right;
    }

    private function numericCompare(mixed $left, mixed $right, callable $compare): bool
    {
        return is_numeric($left) && is_numeric($right) && $compare((float) $left, (float) $right);
    }

    /** @param array<mixed> $values */
    private function containsEquivalent(array $values, mixed $needle): bool
    {
        foreach ($values as $value) {
            if ($this->equal($value, $needle)) {
                return true;
            }
        }

        return false;
    }
}
