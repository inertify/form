<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use Inertify\Form\Support\Rules\MinimumSliderGap;
use InvalidArgumentException;

class Slider extends Field
{
    protected bool $rangeValue = false;

    protected int|float|null $minimum = null;

    protected int|float|null $maximum = null;

    protected int|float $stepValue = 1;

    protected int $minimumSteps = 0;

    public function min(int|float|null $minimum): static
    {
        if ($minimum !== null && $this->maximum !== null && $minimum > $this->maximum) {
            throw new InvalidArgumentException('The slider minimum must not exceed its maximum.');
        }

        $this->minimum = $minimum;

        return $this->option('min', $minimum);
    }

    public function max(int|float|null $maximum): static
    {
        if ($maximum !== null && $this->minimum !== null && $maximum < $this->minimum) {
            throw new InvalidArgumentException('The slider maximum must not be less than its minimum.');
        }

        $this->maximum = $maximum;

        return $this->option('max', $maximum);
    }

    public function step(int|float $step, int|float|null $jump = null): static
    {
        if ($step <= 0) {
            throw new InvalidArgumentException('Slider step must be greater than zero.');
        }

        if ($jump !== null && $jump <= 0) {
            throw new InvalidArgumentException('Slider jump step must be greater than zero.');
        }

        $this->stepValue = $step;
        $this->option('step', $step);

        return $this->option('jumpStep', $jump);
    }

    public function range(bool $range = true): static
    {
        $this->rangeValue = $range;

        return $this->option('range', $range);
    }

    public function minStepsBetween(int $steps): static
    {
        if ($steps < 0) {
            throw new InvalidArgumentException('Minimum steps must be zero or greater.');
        }

        $this->minimumSteps = $steps;

        return $this->option('minStepsBetween', $steps);
    }

    public function unit(?string $unit): static
    {
        return $this->option('unit', $unit);
    }

    /** @param array<mixed> $marks */
    public function marks(array $marks): static
    {
        $normalized = [];

        foreach ($marks as $value => $label) {
            if (is_array($label)) {
                $markValue = $label['value'] ?? null;

                if (! is_numeric($markValue)) {
                    throw new InvalidArgumentException('Slider mark values must be numeric.');
                }
                $normalized[] = $label;
            } else {
                if (! is_numeric($value)) {
                    throw new InvalidArgumentException('Slider mark values must be numeric.');
                }
                $normalized[] = ['value' => (float) $value, 'label' => $label];
            }
        }

        return $this->option('marks', $normalized);
    }

    public function lazy(bool $enabled = true): static
    {
        return $this->option('lazy', $enabled);
    }

    public function hasRangeValue(): bool
    {
        return $this->rangeValue;
    }

    /** @return list<mixed> */
    public function getItemRules(): array
    {
        return array_values(array_filter([
            'numeric',
            $this->minimum === null ? null : 'min:'.$this->minimum,
            $this->maximum === null ? null : 'max:'.$this->maximum,
        ]));
    }

    /** @return list<mixed> */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        if ($rules === ['exclude']) {
            return $rules;
        }

        if (! $this->rangeValue) {
            return [...$rules, ...$this->getItemRules()];
        }

        return [
            ...$rules,
            'array',
            'list',
            'size:2',
            new MinimumSliderGap((float) $this->stepValue * $this->minimumSteps),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function serializedOptions(array $data): array
    {
        return [
            'range' => false,
            'step' => 1,
            'jumpStep' => null,
            'lazy' => false,
            ...parent::serializedOptions($data),
        ];
    }
}
