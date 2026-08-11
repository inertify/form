<?php

declare(strict_types=1);

namespace Inertify\Form\Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Inertify\Form\Uploads\UploadManager;
use Inertify\Form\Uploads\UploadToken;
use Throwable;

final readonly class ValidUploadToken implements ValidationRule
{
    public function __construct(
        private ?string $expectedRulesHash = null,
        private bool $requiresRulesProfile = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail("The {$attribute} upload token is invalid.");

            return;
        }

        try {
            $upload = app(UploadManager::class)->resolve($value);
            $payload = app(UploadToken::class)->decode($value);
        } catch (Throwable) {
            $fail("The {$attribute} upload token is invalid or expired.");

            return;
        }

        if ($upload->isExisting()) {
            return;
        }

        $rulesHash = $payload['rules_hash'] ?? null;

        if ($this->requiresRulesProfile
            && (! is_string($rulesHash) || $this->expectedRulesHash === null || ! hash_equals($this->expectedRulesHash, $rulesHash))) {
            $fail("The {$attribute} upload was not validated for this field.");
        }
    }
}
