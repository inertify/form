<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use DateTimeImmutable;
use Inertify\Form\Support\Rules\ValidTimeValue;
use InvalidArgumentException;

class TimePicker extends Field
{
    protected string $submittedFormat = 'HH:mm';

    protected string $visibleFormat = 'h:mm A';

    protected ?string $minimumTime = null;

    protected ?string $maximumTime = null;

    /** @var list<int> */
    protected array $excludedHours = [];

    /** @var list<int> */
    protected array $excludedMinutes = [];

    /** @var list<int> */
    protected array $excludedSeconds = [];

    /** @var list<string> */
    protected array $excludedValues = [];

    protected bool $includesSeconds = false;

    public function format(string $format): static
    {
        return $this->displayFormat($format);
    }

    public function displayFormat(string $format): static
    {
        $this->visibleFormat = $format;

        return $this->option('displayFormat', $format);
    }

    public function valueFormat(string $format): static
    {
        $this->submittedFormat = $format;

        return $this->option('valueFormat', $format);
    }

    public function min(?string $time): static
    {
        return $this->minTime($time);
    }

    public function max(?string $time): static
    {
        return $this->maxTime($time);
    }

    public function minTime(?string $time): static
    {
        $this->minimumTime = $time;
        $this->assertTimeBounds();

        return $this->option('minTime', $time);
    }

    public function maxTime(?string $time): static
    {
        $this->maximumTime = $time;
        $this->assertTimeBounds();

        return $this->option('maxTime', $time);
    }

    public function step(int $seconds): static
    {
        $this->assertPositiveStep($seconds);

        return $this->option('step', $seconds);
    }

    public function hourStep(int $step): static
    {
        $this->assertPositiveStep($step);

        return $this->option('hourStep', $step);
    }

    public function minuteStep(int $step): static
    {
        $this->assertPositiveStep($step);

        return $this->option('minuteStep', $step);
    }

    public function secondStep(int $step): static
    {
        $this->assertPositiveStep($step);

        return $this->option('secondStep', $step);
    }

    /** @param array<string> $values */
    public function disabledValues(array $values): static
    {
        $this->excludedValues = array_values($values);

        return $this->option('disabledValues', $this->excludedValues);
    }

    /** @param array<int> $hours */
    public function disabledHours(array $hours): static
    {
        $this->excludedHours = $this->normalizeDisabledParts($hours, 0, 23, 'hour');

        return $this->option('disabledHours', $this->excludedHours);
    }

    /** @param array<int> $minutes */
    public function disabledMinutes(array $minutes): static
    {
        $this->excludedMinutes = $this->normalizeDisabledParts($minutes, 0, 59, 'minute');

        return $this->option('disabledMinutes', $this->excludedMinutes);
    }

    /** @param array<int> $seconds */
    public function disabledSeconds(array $seconds): static
    {
        $this->excludedSeconds = $this->normalizeDisabledParts($seconds, 0, 59, 'second');

        return $this->option('disabledSeconds', $this->excludedSeconds);
    }

    public function seconds(bool $seconds = true): static
    {
        return $this->showSeconds($seconds);
    }

    public function showSeconds(bool $seconds = true): static
    {
        $this->includesSeconds = $seconds;

        if ($seconds) {
            if ($this->submittedFormat === 'HH:mm') {
                $this->submittedFormat = 'HH:mm:ss';
            }

            if ($this->visibleFormat === 'h:mm A') {
                $this->visibleFormat = 'h:mm:ss A';
            } elseif ($this->visibleFormat === 'HH:mm') {
                $this->visibleFormat = 'HH:mm:ss';
            }
        } else {
            if ($this->submittedFormat === 'HH:mm:ss') {
                $this->submittedFormat = 'HH:mm';
            }

            if ($this->visibleFormat === 'h:mm:ss A') {
                $this->visibleFormat = 'h:mm A';
            } elseif ($this->visibleFormat === 'HH:mm:ss') {
                $this->visibleFormat = 'HH:mm';
            }
        }

        return $this->option('showSeconds', $seconds)
            ->option('valueFormat', $this->submittedFormat)
            ->option('displayFormat', $this->visibleFormat);
    }

    public function use24HourTime(bool $enabled = true): static
    {
        $this->visibleFormat = $enabled
            ? (str_contains($this->submittedFormat, 'ss') ? 'HH:mm:ss' : 'HH:mm')
            : (str_contains($this->submittedFormat, 'ss') ? 'h:mm:ss A' : 'h:mm A');

        return $this->option('use24HourTime', $enabled)->option('displayFormat', $this->visibleFormat);
    }

    public function clearable(bool $enabled = true): static
    {
        return $this->option('clearable', $enabled);
    }

    /** @return list<mixed> */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        if ($rules === ['exclude']) {
            return $rules;
        }

        return [
            ...$rules,
            'date_format:'.$this->phpValueFormat(),
            new ValidTimeValue(
                $this->phpValueFormat(),
                $this->minimumTime,
                $this->maximumTime,
                $this->excludedHours,
                $this->excludedMinutes,
                $this->includesSeconds ? $this->excludedSeconds : [],
                $this->excludedValues,
            ),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function serializedOptions(array $data): array
    {
        return [
            'use24HourTime' => false,
            'showSeconds' => false,
            'clearable' => false,
            'displayFormat' => $this->visibleFormat,
            'valueFormat' => $this->submittedFormat,
            ...parent::serializedOptions($data),
        ];
    }

    protected function phpValueFormat(): string
    {
        return strtr($this->submittedFormat, [
            'HH' => 'H', 'H' => 'G', 'hh' => 'h', 'h' => 'g',
            'mm' => 'i', 'ss' => 's', 'A' => 'A', 'a' => 'a',
        ]);
    }

    protected function assertPositiveStep(int $step): void
    {
        if ($step < 1) {
            throw new InvalidArgumentException('Time-picker steps must be greater than zero.');
        }
    }

    /**
     * @param  array<int>  $parts
     * @return list<int>
     */
    protected function normalizeDisabledParts(array $parts, int $minimum, int $maximum, string $part): array
    {
        foreach ($parts as $value) {
            if ($value < $minimum || $value > $maximum) {
                throw new InvalidArgumentException("Disabled {$part} values must be between {$minimum} and {$maximum}.");
            }
        }

        return array_values(array_unique($parts));
    }

    protected function assertTimeBounds(): void
    {
        if ($this->minimumTime === null || $this->maximumTime === null) {
            return;
        }

        $format = '!'.$this->phpValueFormat();
        $minimum = DateTimeImmutable::createFromFormat($format, $this->minimumTime);
        $maximum = DateTimeImmutable::createFromFormat($format, $this->maximumTime);

        if ($minimum !== false && $maximum !== false && $minimum > $maximum) {
            throw new InvalidArgumentException('The minimum time must not be after the maximum time.');
        }
    }
}
