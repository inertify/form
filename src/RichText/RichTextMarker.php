<?php

declare(strict_types=1);

namespace Inertify\Form\RichText;

use JsonException;

final class RichTextMarker
{
    public const string UPLOAD_ATTRIBUTE = 'data-inertia-forms-upload';

    public const string STORED_ATTRIBUTE = 'data-inertia-forms-image';

    /** @param array<string, mixed> $metadata */
    public static function encode(string $identifier, array $metadata = []): string
    {
        $json = json_encode(
            ['identifier' => $identifier, 'metadata' => $metadata],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return 'v1.'.rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /** @return array{identifier: string, metadata: array<string, mixed>} */
    public static function decode(string $marker): array
    {
        if (! str_starts_with($marker, 'v1.')) {
            return ['identifier' => $marker, 'metadata' => []];
        }

        $encoded = substr($marker, 3);
        $padding = (4 - strlen($encoded) % 4) % 4;
        $json = base64_decode(strtr($encoded.str_repeat('=', $padding), '-_', '+/'), true);

        if (! is_string($json)) {
            return ['identifier' => $marker, 'metadata' => []];
        }

        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['identifier' => $marker, 'metadata' => []];
        }

        if (! is_array($payload) || ! is_scalar($payload['identifier'] ?? null)) {
            return ['identifier' => $marker, 'metadata' => []];
        }

        return [
            'identifier' => (string) $payload['identifier'],
            'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
        ];
    }
}
