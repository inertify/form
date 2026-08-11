<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Inertify\Form\Support\Rules\DateNotDisabled;
use InvalidArgumentException;

class DatePicker extends Field
{
    protected string $dateMode = 'single';

    protected bool $includesTime = false;

    protected ?string $dateTimezone = null;

    protected string $submittedFormat = 'YYYY-MM-DD';

    protected ?string $minimumDate = null;

    protected ?string $maximumDate = null;

    /** @var list<string> */
    protected array $excludedDates = [];

    public function single(bool $enabled = true): static
    {
        $this->dateMode = $enabled ? 'single' : 'multiple';

        return $this->option('mode', $this->dateMode);
    }

    public function multiple(bool $enabled = true): static
    {
        $this->dateMode = $enabled ? 'multiple' : 'single';

        return $this->option('mode', $this->dateMode);
    }

    public function range(bool $enabled = true): static
    {
        $this->dateMode = $enabled ? 'range' : 'single';

        return $this->option('mode', $this->dateMode);
    }

    public function month(bool $enabled = true): static
    {
        $this->dateMode = $enabled ? 'month' : 'single';

        return $this->option('mode', $this->dateMode);
    }

    public function year(bool $enabled = true): static
    {
        $this->dateMode = $enabled ? 'year' : 'single';

        return $this->option('mode', $this->dateMode);
    }

    public function format(string $format): static
    {
        return $this->displayFormat($format);
    }

    public function displayFormat(string $format): static
    {
        return $this->option('displayFormat', $format);
    }

    public function valueFormat(string $format): static
    {
        $this->submittedFormat = $format;

        return $this->option('valueFormat', $format);
    }

    public function timezone(?string $timezone): static
    {
        $this->dateTimezone = $timezone;

        return $this->option('timezone', $timezone);
    }

    public function min(DateTimeInterface|string|null $date): static
    {
        return $this->minDate($date);
    }

    public function max(DateTimeInterface|string|null $date): static
    {
        return $this->maxDate($date);
    }

    public function minDate(DateTimeInterface|string|null $date): static
    {
        $this->minimumDate = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
        $this->assertDateBounds();

        return $this->option('minDate', $this->minimumDate);
    }

    public function maxDate(DateTimeInterface|string|null $date): static
    {
        $this->maximumDate = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
        $this->assertDateBounds();

        return $this->option('maxDate', $this->maximumDate);
    }

    /** @param array<DateTimeInterface|string> $dates */
    public function disabledDates(array $dates): static
    {
        $this->excludedDates = array_values(array_map(
            fn (DateTimeInterface|string $date): string => $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date,
            $dates,
        ));

        return $this->option('disabledDates', $this->excludedDates);
    }

    /** @param array<mixed> $presets */
    public function presets(array $presets): static
    {
        return $this->option('presets', $presets);
    }

    public function withTime(bool $enabled = true): static
    {
        $this->includesTime = $enabled;

        if ($enabled && $this->submittedFormat === 'YYYY-MM-DD') {
            $this->submittedFormat = 'YYYY-MM-DD HH:mm';
            $this->option('valueFormat', $this->submittedFormat);
        }

        return $this->option('withTime', $enabled);
    }

    public function use24HourTime(bool $enabled = true): static
    {
        return $this->option('use24HourTime', $enabled);
    }

    public function openTo(?string $date): static
    {
        return $this->option('openTo', $date);
    }

    public function clearable(bool $enabled = true): static
    {
        return $this->option('clearable', $enabled);
    }

    public function firstDayOfWeek(int $day): static
    {
        if ($day < 0 || $day > 6) {
            throw new InvalidArgumentException('The first day of the week must be between 0 and 6.');
        }

        return $this->option('weekStartsOn', $day);
    }

    public function hasArrayValue(): bool
    {
        return in_array($this->dateMode, ['range', 'multiple'], true);
    }

    /** @return list<mixed> */
    public function getItemRules(): array
    {
        return array_values(array_filter([
            'date_format:'.$this->phpValueFormat(),
            $this->minimumDate === null ? null : 'after_or_equal:'.$this->minimumDate,
            $this->maximumDate === null ? null : 'before_or_equal:'.$this->maximumDate,
            $this->disabledDateRule(),
        ]));
    }

    /** @return list<mixed> */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        if ($rules === ['exclude']) {
            return $rules;
        }

        if ($this->dateMode === 'year') {
            return [...$rules, 'integer', 'between:1900,2100'];
        }

        if ($this->hasArrayValue()) {
            return [
                ...$rules,
                'array',
                'list',
                ...($this->dateMode === 'range' ? ['size:2'] : []),
            ];
        }

        return array_values(array_filter([
            ...$rules,
            'date_format:'.$this->phpValueFormat(),
            $this->minimumDate === null ? null : 'after_or_equal:'.$this->minimumDate,
            $this->maximumDate === null ? null : 'before_or_equal:'.$this->maximumDate,
            $this->dateMode === 'month' ? null : $this->disabledDateRule(),
        ]));
    }

    public function transformValidatedValue(mixed $value): mixed
    {
        if (! $this->includesTime || $this->dateTimezone === null || ! in_array($this->dateMode, ['single', 'range'], true)) {
            return $value;
        }

        if (is_array($value)) {
            return array_map($this->toUtc(...), $value);
        }

        return is_string($value) ? $this->toUtc($value) : $value;
    }

    /** @param array<string, mixed> $data */
    protected function serializedOptions(array $data): array
    {
        return [
            'mode' => 'single',
            'withTime' => false,
            'valueFormat' => $this->submittedFormat,
            'clearable' => false,
            ...parent::serializedOptions($data),
        ];
    }

    protected function toUtc(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $format = $this->phpValueFormat();

        return CarbonImmutable::createFromFormat($format, $value, $this->dateTimezone)
            ->utc()
            ->format($format);
    }

    protected function phpValueFormat(): string
    {
        if ($this->dateMode === 'month') {
            return 'Y-m';
        }

        return strtr($this->submittedFormat, [
            'YYYY' => 'Y', 'YY' => 'y', 'MMMM' => 'F', 'MMM' => 'M',
            'MM' => 'm', 'M' => 'n', 'DD' => 'd', 'D' => 'j',
            'HH' => 'H', 'mm' => 'i', 'ss' => 's',
        ]);
    }

    protected function disabledDateRule(): ?DateNotDisabled
    {
        return $this->excludedDates === []
            ? null
            : new DateNotDisabled($this->phpValueFormat(), $this->excludedDates);
    }

    protected function assertDateBounds(): void
    {
        if ($this->minimumDate === null || $this->maximumDate === null) {
            return;
        }

        $minimum = strtotime($this->minimumDate);
        $maximum = strtotime($this->maximumDate);

        if ($minimum !== false && $maximum !== false && $minimum > $maximum) {
            throw new InvalidArgumentException('The minimum date must not be after the maximum date.');
        }
    }
}
