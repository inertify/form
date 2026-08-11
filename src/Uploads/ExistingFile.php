<?php

declare(strict_types=1);

namespace Inertify\Form\Uploads;

use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use JsonSerializable;
use Throwable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class ExistingFile implements Arrayable, JsonSerializable
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        private string $key,
        private string $identifier,
        private string $disk,
        private string $path,
        private string $filename,
        private string $name,
        private ?string $previewUrl,
        private ?string $mimeType,
        private int $size,
        private array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function fromDisk(
        string $disk,
        string $path,
        DateTimeInterface|DateInterval|int $expiration = 300,
        bool $withPreview = true,
        array $metadata = [],
    ): self {
        /** @var FilesystemAdapter $filesystem */
        $filesystem = Storage::disk($disk);

        return self::fromFilesystem(
            $filesystem,
            $path,
            $expiration,
            $withPreview,
            $metadata,
            $disk,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function fromFilesystem(
        FilesystemAdapter $filesystem,
        string $path,
        DateTimeInterface|DateInterval|int $expiration = 300,
        bool $withPreview = true,
        array $metadata = [],
        string $disk = 'filesystem',
    ): self {
        $filename = basename($path);
        $identifier = $disk.':'.$path;
        $mimeType = $filesystem->mimeType($path) ?: null;
        $size = $filesystem->size($path);
        $expiresAt = self::expiresAt($expiration);
        $previewUrl = $withPreview ? self::previewUrl($filesystem, $path, $expiresAt) : null;
        $payload = [
            'identifier' => $identifier,
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
            'name' => pathinfo($filename, PATHINFO_FILENAME),
            'preview_url' => $previewUrl,
            'mime_type' => $mimeType,
            'size' => $size,
            'metadata' => $metadata,
        ];

        return self::fromPayload(
            $payload,
            app(UploadToken::class)->encode(
                'existing-upload',
                $payload,
                $expiresAt->getTimestamp(),
            ),
        );
    }

    /**
     * @param  object|iterable<object>  $media
     * @return self|list<self>
     */
    public static function fromMediaLibrary(
        object|iterable $media,
        DateTimeInterface|DateInterval|int $expiration = 300,
        bool $withPreview = true,
    ): self|array {
        if (is_iterable($media)) {
            $files = [];

            foreach ($media as $item) {
                $files[] = self::fromMediaLibrary($item, $expiration, $withPreview);
            }

            /** @var list<self> $files */
            return $files;
        }

        $configuredDisk = $media->disk ?? null;
        $driverDisk = method_exists($media, 'getDiskDriverName')
            ? $media->getDiskDriverName()
            : null;
        $disk = is_string($configuredDisk) && $configuredDisk !== ''
            ? $configuredDisk
            : (is_string($driverDisk) && $driverDisk !== '' ? $driverDisk : 'public');
        $path = method_exists($media, 'getPathRelativeToRoot')
            ? (string) $media->getPathRelativeToRoot()
            : (string) ($media->id ?? 'media');

        return self::fromDisk($disk, $path, $expiration, $withPreview, [
            'media_id' => $media->id ?? null,
            'collection' => $media->collection_name ?? null,
        ]);
    }

    /** @param object|iterable<object> $media
     * @return self|list<self>
     */
    public static function fromMediaLibraryWithoutPreview(object|iterable $media): self|array
    {
        return self::fromMediaLibrary($media, withPreview: false);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload, string $key): self
    {
        return new self(
            $key,
            (string) $payload['identifier'],
            (string) $payload['disk'],
            (string) $payload['path'],
            (string) $payload['filename'],
            (string) $payload['name'],
            isset($payload['preview_url']) ? (string) $payload['preview_url'] : null,
            isset($payload['mime_type']) ? (string) $payload['mime_type'] : null,
            (int) $payload['size'],
            is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
        );
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getDisk(): string
    {
        return $this->disk;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPreviewUrl(): ?string
    {
        return $this->previewUrl;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array{
     *     key: string,
     *     id: string,
     *     identifier: string,
     *     filename: string,
     *     name: string,
     *     previewUrl: string|null,
     *     preview_url: string|null,
     *     mimeType: string|null,
     *     mime_type: string|null,
     *     size: int,
     *     size_in_bytes: int,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'id' => $this->identifier,
            'identifier' => $this->identifier,
            'filename' => $this->filename,
            'name' => $this->name,
            'previewUrl' => $this->previewUrl,
            'preview_url' => $this->previewUrl,
            'mimeType' => $this->mimeType,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'size_in_bytes' => $this->size,
            'metadata' => $this->metadata,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function previewUrl(
        FilesystemAdapter $filesystem,
        string $path,
        DateTimeInterface $expiresAt,
    ): ?string {
        try {
            return $filesystem->temporaryUrl($path, $expiresAt);
        } catch (Throwable) {
            try {
                return $filesystem->url($path);
            } catch (Throwable) {
                return null;
            }
        }
    }

    private static function expiresAt(DateTimeInterface|DateInterval|int $expiration): DateTimeInterface
    {
        if ($expiration instanceof DateTimeInterface) {
            return $expiration;
        }

        if ($expiration instanceof DateInterval) {
            return now()->add($expiration);
        }

        return now()->addSeconds($expiration);
    }
}
