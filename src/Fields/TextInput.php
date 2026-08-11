<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

class TextInput extends Field
{
    public function type(string $type): static
    {
        return $this->option('inputType', $type);
    }

    public function string(bool $enabled = true): static
    {
        $this->setTypeRules($enabled ? ['string'] : []);

        return $this->type('text');
    }

    public function email(bool $enabled = true): static
    {
        $this->setTypeRules($enabled ? ['string', 'email'] : []);

        return $enabled ? $this->type('email') : $this->type('text');
    }

    public function password(bool $enabled = true): static
    {
        $this->withoutModelBinding($enabled);
        $this->setTypeRules($enabled ? ['string'] : []);

        return $enabled ? $this->type('password') : $this->type('text');
    }

    public function number(bool $enabled = true): static
    {
        $this->setTypeRules($enabled ? ['numeric'] : []);

        return $enabled ? $this->type('number') : $this->type('text');
    }

    public function integer(bool $enabled = true): static
    {
        $this->setTypeRules($enabled ? ['integer'] : []);

        return $enabled ? $this->type('number') : $this->type('text');
    }

    public function tel(bool $enabled = true): static
    {
        $this->setTypeRules($enabled ? ['string'] : []);

        return $enabled ? $this->type('tel') : $this->type('text');
    }

    public function url(bool $enabled = true): static
    {
        $this->setTypeRules($enabled ? ['string', 'url'] : []);

        return $enabled ? $this->type('url') : $this->type('text');
    }

    public function color(bool $enabled = true): static
    {
        $this->setTypeRules($enabled ? ['string', 'hex_color'] : []);

        return $enabled ? $this->type('color') : $this->type('text');
    }

    public function search(bool $enabled = true): static
    {
        $this->setTypeRules($enabled ? ['string'] : []);

        return $enabled ? $this->type('search') : $this->type('text');
    }

    public function date(bool $enabled = true): static
    {
        $this->setTypeRules($enabled ? ['date'] : []);

        return $enabled ? $this->type('date') : $this->type('text');
    }

    public function datetime(bool $enabled = true): static
    {
        $this->setTypeRules($enabled ? ['date'] : []);

        return $enabled ? $this->type('datetime-local') : $this->type('text');
    }

    public function time(bool $enabled = true): static
    {
        $this->setTypeRules($enabled ? ['date_format:H:i'] : []);

        return $enabled ? $this->type('time') : $this->type('text');
    }

    public function min(int|float|null $minimum): static
    {
        $this->managedRule('min', $minimum === null ? null : 'min:'.$minimum);

        return $this->option('min', $minimum);
    }

    public function max(int|float|null $maximum): static
    {
        $this->managedRule('max', $maximum === null ? null : 'max:'.$maximum);

        return $this->option('max', $maximum);
    }

    public function step(int|float|string|null $step): static
    {
        return $this->option('step', $step);
    }

    public function minLength(?int $length): static
    {
        $this->managedRule('minLength', $length === null ? null : 'min:'.$length);

        return $this->option('minLength', $length);
    }

    public function maxLength(?int $length): static
    {
        $this->managedRule('maxLength', $length === null ? null : 'max:'.$length);

        return $this->option('maxLength', $length);
    }

    public function pattern(?string $pattern): static
    {
        return $this->option('pattern', $pattern);
    }

    /** @param string|array<mixed>|null $mask */
    public function mask(string|array|null $mask): static
    {
        return $this->option('mask', $mask);
    }

    public function phone(bool $enabled = true): static
    {
        $this->tel($enabled);

        $this->mask($enabled ? '(999) 999-9999' : null);

        return $this->option('phone', $enabled);
    }

    public function creditCard(bool $enabled = true): static
    {
        $this->mask($enabled ? '9999 9999 9999 9999' : null);

        return $this->option('creditCard', $enabled);
    }

    public function clearable(bool $enabled = true): static
    {
        return $this->option('clearable', $enabled);
    }

    public function copyable(bool $enabled = true): static
    {
        return $this->option('copyable', $enabled);
    }

    public function viewable(bool $enabled = true): static
    {
        return $this->option('viewable', $enabled);
    }

    public function kbd(bool|string $kbd = true): static
    {
        return $this->option('kbd', $kbd);
    }

    public function currency(string $locale = 'en-US', string $currency = 'USD', int $decimals = 2): static
    {
        return $this->option('currency', compact('locale', 'currency', 'decimals'));
    }

    public function numberFormat(string $locale = 'en-US', int $decimals = 0): static
    {
        return $this->option('numberFormat', compact('locale', 'decimals'));
    }

    public function parseNumbers(bool $enabled = true): static
    {
        return $this->option('parseNumbers', $enabled);
    }

    public function autocomplete(string|bool|null $autocomplete): static
    {
        return $this->option('autocomplete', $autocomplete);
    }

    public function enterKeyHint(?string $hint): static
    {
        return $this->option('enterKeyHint', $hint);
    }

    public function enterKeySearch(): static
    {
        return $this->enterKeyHint('search');
    }

    public function enterKeySend(): static
    {
        return $this->enterKeyHint('send');
    }

    public function enterKeyNext(): static
    {
        return $this->enterKeyHint('next');
    }

    public function enterKeyDone(): static
    {
        return $this->enterKeyHint('done');
    }

    public function enterKeyGo(): static
    {
        return $this->enterKeyHint('go');
    }

    public function inputMode(?string $mode): static
    {
        return $this->option('inputMode', $mode);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function serializedOptions(array $data): array
    {
        return [
            'inputType' => 'text',
            'clearable' => false,
            'copyable' => false,
            'viewable' => false,
            ...parent::serializedOptions($data),
        ];
    }

    /** @param list<string> $rules */
    protected function setTypeRules(array $rules): void
    {
        $this->managedRule('typeBase', $rules[0] ?? null);
        $this->managedRule('typeFormat', $rules[1] ?? null);
    }
}
