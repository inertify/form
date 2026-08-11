<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use Closure;

class RichText extends Field
{
    protected ?int $maximumLength = null;

    protected ?UploadConfig $richTextImages = null;

    public function maxLength(?int $length): static
    {
        $this->maximumLength = $length;

        return $this->option('maxLength', $length);
    }

    public function imageUploads(Closure|bool $configuration = true): static
    {
        if ($configuration === false) {
            $this->richTextImages = null;

            return $this->option('imageUploads', null);
        }

        $uploads = UploadConfig::make($this->getImageUploadFieldName())->multiple()->image();

        if ($configuration instanceof Closure) {
            $result = $configuration($uploads);

            if ($result instanceof UploadConfig) {
                $uploads = $result;
            }
        }

        $this->richTextImages = $uploads;

        return $this;
    }

    public function hasImageUploads(): bool
    {
        return $this->richTextImages !== null;
    }

    public function getImageUploadFieldName(): string
    {
        return str_replace('.', '_', $this->getName()).'_images';
    }

    public function getImageUploadField(): ?UploadConfig
    {
        return $this->richTextImages;
    }

    /** @return list<mixed> */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        return $rules === ['exclude'] ? $rules : [...$rules, 'string'];
    }

    /** @param array<string, mixed> $data */
    protected function serializedOptions(array $data): array
    {
        return [
            ...parent::serializedOptions($data),
            'imageUploads' => $this->richTextImages?->getUploadConfiguration($data),
            'imageUploadField' => $this->hasImageUploads() ? $this->getImageUploadFieldName() : null,
        ];
    }
}
