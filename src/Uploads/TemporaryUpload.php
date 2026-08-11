<?php

declare(strict_types=1);

namespace Inertify\Form\Uploads;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class TemporaryUpload implements Arrayable, JsonSerializable
{
    public function __construct(
        private string $identifier,
        private ?string $disk,
        private string $path,
        private string $name,
        private ?string $mimeType,
        private int $size,
        private string $kind = 'temporary',
        private ?string $rulesHash = null,
        private int $createdAt = 0,
        private ?string $contentHash = null,
    ) {}

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getDisk(): ?string
    {
        return $this->disk;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getRulesHash(): ?string
    {
        return $this->rulesHash;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getContentHash(): ?string
    {
        return $this->contentHash;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'disk' => $this->disk,
            'path' => $this->path,
            'name' => $this->name,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'kind' => $this->kind,
            'rules_hash' => $this->rulesHash,
            'created_at' => $this->createdAt,
            'content_hash' => $this->contentHash,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
