<?php

declare(strict_types=1);

namespace Inertify\Form\Uploads;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Inertify\Form\Uploads\Exceptions\InvalidUploadToken;

final readonly class UploadToken
{
    public function __construct(private Encrypter $encrypter) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function encode(string $purpose, array $payload, ?int $expiresAt = null): string
    {
        return $this->encrypter->encrypt([
            'version' => 1,
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
            'payload' => $payload,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(string $token, ?string $purpose = null): array
    {
        try {
            $decoded = $this->encrypter->decrypt($token);
        } catch (DecryptException) {
            throw InvalidUploadToken::malformed();
        }

        if (! is_array($decoded)
            || ($decoded['version'] ?? null) !== 1
            || ! is_string($decoded['purpose'] ?? null)
            || ! is_array($decoded['payload'] ?? null)) {
            throw InvalidUploadToken::malformed();
        }

        if ($purpose !== null && $decoded['purpose'] !== $purpose) {
            throw InvalidUploadToken::unexpectedPurpose();
        }

        $expiresAt = $decoded['expires_at'] ?? null;

        if (is_int($expiresAt) && $expiresAt < now()->getTimestamp()) {
            throw InvalidUploadToken::expired();
        }

        /** @var array<string, mixed> $payload */
        $payload = $decoded['payload'];

        $payload['_purpose'] = $decoded['purpose'];

        return $payload;
    }
}
