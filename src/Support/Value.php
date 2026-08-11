<?php

declare(strict_types=1);

namespace Inertify\Form\Support;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Stringable;
use UnitEnum;

final class Value
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function resolve(mixed $value, array $parameters = []): mixed
    {
        if (! $value instanceof Closure) {
            return $value;
        }

        return Container::getInstance()->call($value, $parameters);
    }

    public static function normalize(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof Arrayable) {
            return self::normalize($value->toArray());
        }

        if ($value instanceof JsonSerializable) {
            return self::normalize($value->jsonSerialize());
        }

        if (is_array($value)) {
            return array_map(self::normalize(...), $value);
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return $value;
    }
}
