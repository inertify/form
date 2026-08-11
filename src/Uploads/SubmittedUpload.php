<?php

declare(strict_types=1);

namespace Inertify\Form\Uploads;

use Illuminate\Support\Str;
use LogicException;

final readonly class SubmittedUpload
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        private UploadManager $manager,
        private string $key,
        private array $payload,
    ) {}

    public function isNew(): bool
    {
        return ($this->payload['_purpose'] ?? null) === 'temporary-upload';
    }

    public function isExisting(): bool
    {
        return ($this->payload['_purpose'] ?? null) === 'existing-upload';
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getIdentifier(): string
    {
        return (string) $this->payload['identifier'];
    }

    public function getName(): string
    {
        return (string) ($this->payload['name'] ?? $this->payload['filename'] ?? 'upload');
    }

    public function getMimeType(): ?string
    {
        return isset($this->payload['mime_type']) ? (string) $this->payload['mime_type'] : null;
    }

    public function getSize(): int
    {
        return (int) ($this->payload['size'] ?? 0);
    }

    public function getPath(): string
    {
        return (string) $this->payload['path'];
    }

    public function getDisk(): ?string
    {
        return isset($this->payload['disk']) ? (string) $this->payload['disk'] : null;
    }

    public function getUploadedFile(): ?UploadedFile
    {
        return $this->isNew() && ($this->payload['kind'] ?? null) !== 'direct'
            ? $this->manager->materialize($this->payload)
            : null;
    }

    public function getRemoteFile(): ?RemoteFile
    {
        if (! $this->isNew() || ($this->payload['kind'] ?? null) !== 'direct') {
            return null;
        }

        return new RemoteFile(
            (string) $this->payload['disk'],
            (string) $this->payload['path'],
            $this->getName(),
            $this->getMimeType(),
            $this->getSize(),
        );
    }

    public function getExistingFile(): ?ExistingFile
    {
        return $this->isExisting() ? ExistingFile::fromPayload($this->payload, $this->key) : null;
    }

    /**
     * @param  array<string, mixed>|string  $options
     */
    public function store(
        string $path = '',
        array|string $options = [],
        bool $deleteTemporary = true,
    ): string {
        $extension = pathinfo($this->getName(), PATHINFO_EXTENSION);
        $filename = Str::uuid()->toString().($extension === '' ? '' : '.'.$extension);

        return $this->storeAs($path, $filename, $options, $deleteTemporary);
    }

    /**
     * @param  array<string, mixed>|string  $options
     */
    public function storeAs(
        string $path,
        string $name,
        array|string $options = [],
        bool $deleteTemporary = true,
    ): string {
        if ($this->isExisting()) {
            throw new LogicException('Existing files cannot be stored as new uploads.');
        }

        return $this->manager->promote($this->payload, $path, $name, $options, $deleteTemporary);
    }
}
