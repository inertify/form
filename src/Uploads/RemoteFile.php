<?php

declare(strict_types=1);

namespace Inertify\Form\Uploads;

final readonly class RemoteFile
{
    public function __construct(
        private string $disk,
        private string $path,
        private string $name,
        private ?string $mimeType,
        private int $size,
    ) {}

    public function getDisk(): string
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
}
