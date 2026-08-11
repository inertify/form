<?php

declare(strict_types=1);

namespace Inertify\Form\Uploads;

use Illuminate\Contracts\Support\Arrayable;
use Inertify\Form\Uploads\Exceptions\InvalidUploadToken;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class UploadRules implements Arrayable, JsonSerializable
{
    /**
     * @param  list<string>  $rules
     * @param  list<class-string>  $validators
     */
    public function __construct(
        private array $rules = [],
        private array $validators = [],
        private ?string $disk = null,
    ) {}

    /**
     * @param  list<string>  $rules
     * @param  list<class-string>  $validators
     */
    public static function make(array $rules = [], array $validators = [], ?string $disk = null): self
    {
        return new self($rules, $validators, $disk);
    }

    public function token(): string
    {
        $lifetime = (int) config('inertia-forms.file_uploads.temporary_uploads.lifetime', 3600);

        return app(UploadToken::class)->encode(
            'upload-rules',
            $this->toArray(),
            now()->addSeconds(max(0, $lifetime))->getTimestamp(),
        );
    }

    public static function fromToken(string $token): self
    {
        $payload = app(UploadToken::class)->decode($token, 'upload-rules');
        $rules = $payload['rules'] ?? [];
        $validators = $payload['validators'] ?? [];
        $disk = $payload['disk'] ?? null;

        if (! is_array($rules) || ! is_array($validators) || ($disk !== null && ! is_string($disk))) {
            throw InvalidUploadToken::malformed();
        }

        $normalizedRules = array_values(array_filter($rules, is_string(...)));
        $normalizedValidators = array_values(array_filter(
            $validators,
            fn (mixed $validator): bool => is_string($validator) && class_exists($validator),
        ));

        if (count($normalizedRules) !== count($rules)
            || count($normalizedValidators) !== count($validators)) {
            throw InvalidUploadToken::malformed();
        }

        return new self(
            $normalizedRules,
            $normalizedValidators,
            $disk,
        );
    }

    /** @return list<string> */
    public function rules(): array
    {
        return $this->rules;
    }

    /** @return list<class-string> */
    public function validators(): array
    {
        return $this->validators;
    }

    public function disk(): ?string
    {
        return $this->disk;
    }

    public function hash(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_THROW_ON_ERROR));
    }

    /** @return array{rules: list<string>, validators: list<class-string>, disk: string|null} */
    public function toArray(): array
    {
        return [
            'rules' => $this->rules,
            'validators' => $this->validators,
            'disk' => $this->disk,
        ];
    }

    /** @return array{rules: list<string>, validators: list<class-string>, disk: string|null} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
