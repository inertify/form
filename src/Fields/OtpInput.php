<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

class OtpInput extends Field
{
    public function getComponent(): string
    {
        return 'Otp';
    }

    /** @param array<string, mixed> $data
     * @return list<mixed>
     */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        if (in_array('exclude', $rules, true)) {
            return ['exclude'];
        }

        $format = array_values(array_filter($rules, fn (mixed $rule): bool => is_string($rule) && in_array($rule, ['numeric', 'alpha_num'], true)));
        $rest = array_values(array_filter($rules, fn (mixed $rule): bool => ! in_array($rule, $format, true)));

        return [...$format, ...$rest];
    }

    public function length(int $length): static
    {
        $this->option('length', $length);
        $numeric = (bool) ($this->options['numeric'] ?? false);
        $this->managedRule('length', ($numeric ? 'digits:' : 'size:').$length);

        return $this->option('length', $length);
    }

    public function numeric(bool $numeric = true): static
    {
        $this->managedRule('format', $numeric ? 'numeric' : null);

        if (isset($this->options['length'])) {
            $this->managedRule('length', ($numeric ? 'digits:' : 'size:').$this->options['length']);
        }

        return $this->option('numeric', $numeric)->option('alphanumeric', false);
    }

    public function alphanumeric(bool $enabled = true): static
    {
        $this->managedRule('format', $enabled ? 'alpha_num' : null);

        if (isset($this->options['length'])) {
            $this->managedRule('length', 'size:'.$this->options['length']);
        }

        return $this->option('alphanumeric', $enabled)->option('numeric', ! $enabled);
    }

    public function password(bool $enabled = true): static
    {
        $this->withoutModelBinding($enabled);

        return $this->option('password', $enabled);
    }

    public function mask(bool $mask = true): static
    {
        return $this->option('mask', $mask);
    }

    public function autoSubmit(bool $enabled = true): static
    {
        return $this->option('autoSubmit', $enabled);
    }

    public function webOtp(bool $enabled = true): static
    {
        return $this->option('webOtp', $enabled);
    }

    /** @param array<string, mixed> $data */
    protected function serializedOptions(array $data): array
    {
        return [
            'numeric' => true,
            'webOtp' => true,
            ...parent::serializedOptions($data),
        ];
    }
}
