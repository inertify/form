<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use Illuminate\Validation\Rule;
use Inertify\Form\Support\Rules\ValidLinkUrl;
use InvalidArgumentException;

class Link extends Field
{
    protected bool $structuredValue = false;

    protected bool $includesLabel = false;

    protected bool $includesTarget = false;

    protected bool $schemeRequired = false;

    /** @var list<string> */
    protected array $schemes = ['http', 'https'];

    public function plain(bool $enabled = true): static
    {
        $this->structuredValue = ! $enabled;

        if ($enabled) {
            $this->includesLabel = false;
            $this->includesTarget = false;
        }

        return $this->option('mode', $enabled ? 'plain' : 'structured');
    }

    public function structured(bool $enabled = true): static
    {
        $this->structuredValue = $enabled;

        return $this->option('mode', $enabled ? 'structured' : 'plain');
    }

    public function withLabel(bool $enabled = true): static
    {
        $this->includesLabel = $enabled;
        $this->structuredValue = $enabled || $this->structuredValue;

        return $this->option('withLabel', $enabled);
    }

    public function withTarget(bool $enabled = true): static
    {
        $this->includesTarget = $enabled;
        $this->structuredValue = $enabled || $this->structuredValue;

        return $this->option('withTarget', $enabled);
    }

    public function requireScheme(bool $enabled = true): static
    {
        $this->schemeRequired = $enabled;

        return $this->option('requireScheme', $enabled);
    }

    /** @param array<string>|string ...$schemes */
    public function allowedSchemes(array|string ...$schemes): static
    {
        $flattened = [];
        foreach ($schemes as $scheme) {
            foreach (is_array($scheme) ? $scheme : [$scheme] as $value) {
                $value = strtolower(trim($value));
                $value = (string) preg_replace('#:(//)?$#', '', $value);

                if (! preg_match('/^[a-z][a-z0-9+.-]*$/', $value)
                    || in_array($value, ['javascript', 'data', 'vbscript', 'file'], true)) {
                    throw new InvalidArgumentException("Unsafe or malformed link scheme [{$value}].");
                }

                $flattened[] = $value;
            }
        }

        if ($flattened === []) {
            throw new InvalidArgumentException('At least one safe link scheme is required.');
        }
        $this->schemes = array_values(array_unique($flattened));

        return $this->option('allowedSchemes', $this->schemes);
    }

    public function normalizeValue(mixed $value): mixed
    {
        if (! $this->structuredValue) {
            return is_array($value) ? (string) ($value['url'] ?? '') : $value;
        }

        $value = is_array($value) ? $value : ['url' => $value];
        $normalized = ['url' => (string) ($value['url'] ?? '')];

        if ($this->includesLabel) {
            $normalized['label'] = (string) ($value['label'] ?? '');
        }

        if ($this->includesTarget) {
            $normalized['target'] = (string) ($value['target'] ?? '');
        }

        return $normalized;
    }

    public function emptyValue(): mixed
    {
        return $this->normalizeValue(null);
    }

    public function isStructured(): bool
    {
        return $this->structuredValue;
    }

    public function includesLabel(): bool
    {
        return $this->includesLabel;
    }

    public function includesTarget(): bool
    {
        return $this->includesTarget;
    }

    /** @param array<string, mixed> $data
     * @param  array<string, mixed>|null  $row
     * @return list<mixed>
     */
    public function getUrlRules(array $data = [], ?array $row = null): array
    {
        return [
            $this->isRequiredFor($data, $row) ? 'required' : 'nullable',
            'string',
            new ValidLinkUrl($this->schemes, $this->schemeRequired),
        ];
    }

    /** @return list<mixed> */
    public function getTargetRules(): array
    {
        return ['nullable', 'string', Rule::in(['_self', '_blank', '_parent', '_top'])];
    }

    /** @return list<mixed> */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        if ($rules === ['exclude']) {
            return $rules;
        }

        return $this->structuredValue
            ? [...$rules, 'array']
            : [...$rules, ...array_slice($this->getUrlRules($data, $row), 1)];
    }
}
