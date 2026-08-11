<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use Inertify\Form\Contracts\ValidatesFileUploads;

class Composer extends Field
{
    protected bool $attachmentsEnabled = false;

    protected ?int $maximumTextLength = null;

    protected ?File $composerAttachments = null;

    public function allowAttachments(bool $enabled = true): static
    {
        $this->attachmentsEnabled = $enabled;

        return $this->option('allowAttachments', $enabled);
    }

    public function attachments(bool $enabled = true): static
    {
        return $this->allowAttachments($enabled);
    }

    public function storeWithForm(bool $enabled = true): static
    {
        $this->attachmentField()->storeWithForm($enabled);

        return $this->option('storeWithForm', $enabled);
    }

    public function storeAttachmentsWithForm(bool $enabled = true): static
    {
        return $this->storeWithForm($enabled);
    }

    public function reorderable(bool $enabled = true): static
    {
        $this->attachmentField()->reorderable($enabled);

        return $this->option('reorderable', $enabled);
    }

    /** @param string|array<string>|null $types */
    public function acceptedFileTypes(string|array|null $types): static
    {
        $this->attachmentField()->acceptedFileTypes($types);

        return $this->option('acceptedFileTypes', $types);
    }

    public function maxFileSize(?int $kilobytes): static
    {
        $this->attachmentField()->maxFileSize($kilobytes);

        return $this->option('maxFileSize', $kilobytes);
    }

    public function maxLength(?int $length): static
    {
        $this->maximumTextLength = $length;

        return $this->option('maxLength', $length);
    }

    public function temporaryUploadUrl(?string $url): static
    {
        $this->attachmentField()->temporaryUploadUrl($url);

        return $this->option('temporaryUploadUrl', $url);
    }

    public function temporaryUploadDeleteUrl(?string $url): static
    {
        $this->attachmentField()->temporaryUploadDeleteUrl($url);

        return $this->option('temporaryUploadDeleteUrl', $url);
    }

    public function chunked(int|bool $size = true): static
    {
        $this->attachmentField()->chunked($size);

        return $this->option('chunked', $size !== false);
    }

    public function directToStorage(string|bool|null $disk = true): static
    {
        $this->attachmentField()->directToStorage($disk);

        return $this->option('directToStorage', $disk !== false);
    }

    public function uploadDisk(?string $disk): static
    {
        $this->attachmentField()->uploadDisk($disk);

        return $this->option('uploadDisk', $disk);
    }

    public function partSize(?int $bytes): static
    {
        $this->attachmentField()->partSize($bytes);

        return $this->option('uploadPartSize', $bytes);
    }

    public function multipartThreshold(?int $bytes): static
    {
        $this->attachmentField()->multipartThreshold($bytes);

        return $this->option('uploadMultipartThreshold', $bytes);
    }

    public function uploadRoutes(string $routeName): static
    {
        $this->attachmentField()->uploadRoutes($routeName);

        return $this->option('uploadRoutes', $routeName);
    }

    public function requireValidatedUploads(bool $required = true, ?string $rulesToken = null): static
    {
        $this->attachmentField()->requireValidatedUploads($required, $rulesToken);

        return $this->option('requireValidatedUploads', $required);
    }

    /** @param class-string<ValidatesFileUploads> $validator */
    public function validateUploadsUsing(string $validator): static
    {
        $this->attachmentField()->validateUploadsUsing($validator);

        return $this->option('validateUploadsUsing', true);
    }

    public function hasAttachments(): bool
    {
        return $this->attachmentsEnabled;
    }

    public function getAttachmentFieldName(): string
    {
        return $this->getName().'.attachments';
    }

    public function getAttachmentField(): File
    {
        return $this->attachmentField();
    }

    /** @return list<mixed> */
    public function getTextRules(): array
    {
        return array_values(array_filter([
            $this->attachmentsEnabled ? 'nullable' : 'string',
            $this->attachmentsEnabled ? 'string' : null,
            $this->maximumTextLength === null ? null : 'max:'.$this->maximumTextLength,
        ]));
    }

    /** @return list<mixed> */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        if ($rules === ['exclude']) {
            return $rules;
        }

        return $this->attachmentsEnabled
            ? [...$rules, 'array']
            : [...$rules, ...$this->getTextRules()];
    }

    public function normalizeValue(mixed $value): mixed
    {
        if (! $this->attachmentsEnabled) {
            return is_string($value) || $value === null ? $value : (is_scalar($value) ? (string) $value : null);
        }

        if (! is_array($value)) {
            return [
                'text' => is_string($value) ? $value : '',
                'attachments' => [],
            ];
        }

        return [
            'text' => is_string($value['text'] ?? null) ? $value['text'] : '',
            'attachments' => is_array($value['attachments'] ?? null) ? array_values($value['attachments']) : [],
        ];
    }

    public function emptyValue(): mixed
    {
        return $this->attachmentsEnabled ? ['text' => '', 'attachments' => []] : null;
    }

    /** @param array<string, mixed> $data */
    protected function serializedOptions(array $data): array
    {
        $upload = $this->attachmentsEnabled ? $this->attachmentField()->getUploadConfiguration($data) : [];

        return [
            'allowAttachments' => false,
            'reorderable' => false,
            ...parent::serializedOptions($data),
            ...$upload,
        ];
    }

    protected function attachmentField(): File
    {
        return $this->composerAttachments ??= File::make($this->getAttachmentFieldName())->multiple();
    }
}
