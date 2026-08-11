<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Inertify\Form\Contracts\BuildsUploadDescriptors;
use Inertify\Form\Contracts\ValidatesFileUploads;
use Inertify\Form\Support\Rules\ValidUploadToken;
use Inertify\Form\Support\Value;
use Inertify\Form\Uploads\ExistingFile;
use Inertify\Form\Uploads\UploadRules;
use InvalidArgumentException;
use Throwable;

class File extends Field
{
    /** @var array<string, mixed>|Closure|null */
    protected array|Closure|null $uploadDescriptor = null;

    protected string $uploadStrategy = 'temporary';

    protected ?string $configuredUploadDisk = null;

    protected ?string $configuredRulesToken = null;

    protected bool $requiresRulesToken = false;

    protected ?bool $validatedUploadGuard = null;

    protected bool $usesCustomTemporaryUrl = false;

    protected ?string $configuredUploadRoute = null;

    protected ?string $existingFileDisk = null;

    protected ?string $configuredMediaCollection = null;

    /** @var class-string<ValidatesFileUploads>|null */
    protected ?string $uploadValidator = null;

    /** @var array<string, string> */
    protected array $uploadValidationRules = [];

    protected ?int $minimumFiles = null;

    protected ?int $maximumFiles = null;

    protected ?int $minimumSize = null;

    protected ?int $maximumSize = null;

    public function image(bool $image = true): static
    {
        $this->option('image', $image);

        $this->setUploadRule('image', $image ? 'image' : null);

        return $image ? $this->accept('image/*') : $this;
    }

    /** @param string|array<string>|null $accept */
    public function accept(string|array|null $accept): static
    {
        $this->setUploadRule('accept', $this->acceptRule($accept));

        return $this->option('accept', $accept);
    }

    public function multiple(bool $multiple = true): static
    {
        $this->managedRule('multiple', $multiple ? 'array' : null);

        return $this->option('multiple', $multiple);
    }

    public function maxFiles(?int $maximum): static
    {
        if ($maximum !== null && ($maximum < 0 || ($this->minimumFiles !== null && $maximum < $this->minimumFiles))) {
            throw new InvalidArgumentException('Maximum files must be non-negative and not be less than the minimum.');
        }

        $this->maximumFiles = $maximum;
        $this->managedRule('maxFiles', $maximum === null ? null : 'max:'.$maximum);

        return $this->option('maxFiles', $maximum);
    }

    public function minFiles(?int $minimum): static
    {
        if ($minimum !== null && ($minimum < 0 || ($this->maximumFiles !== null && $minimum > $this->maximumFiles))) {
            throw new InvalidArgumentException('Minimum files must be non-negative and not exceed the maximum.');
        }

        $this->minimumFiles = $minimum;
        $this->managedRule('minFiles', $minimum === null ? null : 'min:'.$minimum);

        return $this->option('minFiles', $minimum);
    }

    public function maxSize(?int $kilobytes): static
    {
        if ($kilobytes !== null && ($kilobytes < 0 || ($this->minimumSize !== null && $kilobytes < $this->minimumSize))) {
            throw new InvalidArgumentException('Maximum file size must be non-negative and not be less than the minimum.');
        }

        $this->maximumSize = $kilobytes;
        $this->setUploadRule('maxSize', $kilobytes === null ? null : 'max:'.$kilobytes);

        return $this->option('maxSize', $kilobytes);
    }

    public function minSize(?int $kilobytes): static
    {
        if ($kilobytes !== null && ($kilobytes < 0 || ($this->maximumSize !== null && $kilobytes > $this->maximumSize))) {
            throw new InvalidArgumentException('Minimum file size must be non-negative and not exceed the maximum.');
        }

        $this->minimumSize = $kilobytes;
        $this->setUploadRule('minSize', $kilobytes === null ? null : 'min:'.$kilobytes);

        return $this->option('minSize', $kilobytes);
    }

    /** @param array<string, int>|int|string|null $width */
    public function dimensions(array|int|string|null $width, ?int $height = null): static
    {
        $dimensions = is_int($width) ? ['width' => $width, 'height' => $height ?? $width] : $width;
        $rule = is_array($dimensions)
            ? 'dimensions:'.collect($dimensions)->map(fn (mixed $value, string $key): string => $key.'='.$value)->implode(',')
            : $dimensions;
        $this->setUploadRule('dimensions', $rule === null || str_starts_with($rule, 'dimensions:') ? $rule : 'dimensions:'.$rule);

        return $this->option('dimensions', $dimensions);
    }

    public function minDimensions(?int $width, ?int $height = null): static
    {
        $parts = array_filter([
            $width === null ? null : 'min_width='.$width,
            $height === null ? null : 'min_height='.$height,
        ]);
        $this->setUploadRule('minDimensions', $parts === [] ? null : 'dimensions:'.implode(',', $parts));

        return $this->option('minDimensions', compact('width', 'height'));
    }

    public function maxDimensions(?int $width, ?int $height = null): static
    {
        $parts = array_filter([
            $width === null ? null : 'max_width='.$width,
            $height === null ? null : 'max_height='.$height,
        ]);
        $this->setUploadRule('maxDimensions', $parts === [] ? null : 'dimensions:'.implode(',', $parts));

        return $this->option('maxDimensions', compact('width', 'height'));
    }

    public function storeWithForm(bool $enabled = true): static
    {
        $this->uploadStrategy = $enabled ? 'form' : 'temporary';

        return $this->option('storeWithForm', $enabled);
    }

    public function temporaryUploadUrl(?string $url): static
    {
        $this->usesCustomTemporaryUrl = $url !== null;

        return $this->option('temporaryUploadUrl', $url);
    }

    public function temporaryUploadDeleteUrl(?string $url): static
    {
        return $this->option('temporaryUploadDeleteUrl', $url);
    }

    public function chunked(int|bool $size = true): static
    {
        $enabled = $size !== false;
        $this->uploadStrategy = $enabled ? 'chunked' : 'temporary';
        $this->option('chunked', $enabled);

        return is_int($size) ? $this->option('chunkSize', $size) : $this;
    }

    public function directToStorage(string|bool|null $disk = true): static
    {
        $enabled = $disk !== false;
        $this->uploadStrategy = $enabled ? 'direct' : 'temporary';

        if (is_string($disk)) {
            $this->configuredUploadDisk = $disk;
        }

        return $this->option('directToStorage', $enabled);
    }

    public function uploadDisk(?string $disk): static
    {
        $this->configuredUploadDisk = $disk;

        return $this->option('uploadDisk', $disk);
    }

    public function partSize(?int $bytes): static
    {
        return $this->option('uploadPartSize', $bytes);
    }

    public function multipartThreshold(?int $bytes): static
    {
        return $this->option('uploadMultipartThreshold', $bytes);
    }

    public function uploadRoutes(string $routeName): static
    {
        $this->configuredUploadRoute = $routeName;

        return $this->option('uploadRoutes', $routeName);
    }

    public function requireValidatedUploads(bool $required = true, ?string $rulesToken = null): static
    {
        $this->requiresRulesToken = $required;
        $this->validatedUploadGuard = $required;
        $this->configuredRulesToken = $rulesToken;

        return $this->option('requireValidatedUploads', $required);
    }

    public function uploadRulesToken(?string $token): static
    {
        $this->configuredRulesToken = $token;
        $this->requiresRulesToken = $token !== null;

        return $this;
    }

    /** @param class-string<ValidatesFileUploads> $validator */
    public function validateUploadsUsing(string $validator): static
    {
        $this->uploadValidator = $validator;

        return $this->option('validateUploadsUsing', true);
    }

    public function mediaCollection(?string $collection): static
    {
        $this->configuredMediaCollection = $collection;

        return $this->option('mediaCollection', $collection);
    }

    /** @param array<mixed>|Closure $files */
    public function existingFiles(array|Closure $files): static
    {
        return $this->option('existingFiles', $files);
    }

    /** @param array<string, mixed>|Closure $descriptor */
    public function upload(array|Closure $descriptor): static
    {
        $this->uploadDescriptor = $descriptor;

        return $this;
    }

    /** @return class-string<ValidatesFileUploads>|null */
    public function getUploadValidator(): ?string
    {
        return $this->uploadValidator;
    }

    /** @param array<string, mixed> $data */
    protected function serializedOptions(array $data): array
    {
        $options = parent::serializedOptions($data);
        $descriptor = Value::resolve($this->uploadDescriptor, [
            'data' => $data,
            'field' => $this,
        ]);

        $container = Container::getInstance();

        $profile = $this->uploadProfile();
        $requiresProfile = $this->requiresUploadProfile();

        if ($this->configuredRulesToken === null && $requiresProfile) {
            $this->configuredRulesToken = $profile->token();
            $this->requiresRulesToken = true;
        }

        $transport = [];

        if ($container->bound(BuildsUploadDescriptors::class)) {
            $routeFree = $descriptor !== null || ($this->uploadStrategy === 'temporary' && $this->usesCustomTemporaryUrl);
            $transport = $container->make(BuildsUploadDescriptors::class)->flatProps(
                $routeFree ? 'form' : $this->uploadStrategy,
                $this->configuredUploadDisk,
                $this->configuredRulesToken,
                $this->requiresRulesToken,
                $this->configuredUploadRoute,
            );

            if ($routeFree) {
                $transport['storeWithForm'] = $this->uploadStrategy === 'form';
                $transport['chunked'] = $this->uploadStrategy === 'chunked';
                $transport['directToStorage'] = $this->uploadStrategy === 'direct';

                if (is_array($transport['upload'] ?? null)) {
                    $transport['upload']['strategy'] = $this->uploadStrategy;
                }
            }
        }

        if ($descriptor !== null) {
            $transport['upload'] = Value::normalize($descriptor);
        }

        $transport = $this->applyTransportOverrides($transport, $options);

        return [
            ...$options,
            ...$transport,
        ];
    }

    /**
     * Make field methods authoritative over package-level transport defaults.
     *
     * @param  array<string, mixed>  $transport
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function applyTransportOverrides(array $transport, array $options): array
    {
        $flatKeys = [
            'storeWithForm',
            'temporaryUploadUrl',
            'temporaryUploadDeleteUrl',
            'chunked',
            'chunkSize',
            'directToStorage',
            'uploadDisk',
            'uploadPartSize',
            'uploadMultipartThreshold',
        ];

        foreach ($flatKeys as $key) {
            if (array_key_exists($key, $options)) {
                $transport[$key] = $options[$key];
            }
        }

        $upload = $transport['upload'] ?? null;

        if (! is_array($upload)) {
            return $transport;
        }

        $nestedOverrides = [
            'temporaryUploadUrl' => 'endpoints.store.url',
            'temporaryUploadDeleteUrl' => 'endpoints.destroy.url',
            'chunkSize' => 'limits.chunkSizeBytes',
            'uploadDisk' => 'disk',
            'uploadPartSize' => 'limits.partSizeBytes',
            'uploadMultipartThreshold' => 'limits.multipartThresholdBytes',
        ];

        foreach ($nestedOverrides as $option => $path) {
            if (array_key_exists($option, $options)) {
                data_set($upload, $path, $options[$option]);
            }
        }

        foreach ([
            'temporaryUploadUrl' => ['endpoints.store.method', 'POST'],
            'temporaryUploadDeleteUrl' => ['endpoints.destroy.method', 'DELETE'],
        ] as $option => [$path, $method]) {
            if (is_string($options[$option] ?? null)) {
                data_set($upload, $path, $method);
            }
        }

        if (array_key_exists('maxSize', $options)) {
            data_set($upload, 'limits.maxSizeKiB', $options['maxSize']);
            data_set($upload, 'limits.directMaxSizeKiB', $options['maxSize']);
        }

        $transport['upload'] = $upload;

        return $transport;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function getUploadConfiguration(array $data = []): array
    {
        return $this->serializedOptions($data);
    }

    public function isMultiple(): bool
    {
        return (bool) ($this->options['multiple'] ?? false);
    }

    public function emptyValue(): mixed
    {
        return $this->isMultiple() ? [] : null;
    }

    /** @return list<string> */
    public function getUploadRules(): array
    {
        return array_values($this->uploadValidationRules);
    }

    /** @return list<mixed> */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        if ($rules === ['exclude']) {
            return $rules;
        }

        if ($this->isMultiple()) {
            return $rules;
        }

        return [...$rules, ...$this->getItemRules()];
    }

    /** @return list<mixed> */
    public function getItemRules(): array
    {
        if ($this->uploadStrategy === 'form') {
            return ['file', ...array_values($this->uploadValidationRules)];
        }

        if (! $this->guardsUploadTokens()) {
            return [];
        }

        $requiresProfile = $this->requiresUploadProfile();

        return [new ValidUploadToken(
            $requiresProfile ? $this->expectedUploadRulesHash() : null,
            $requiresProfile,
        )];
    }

    public function storesWithForm(): bool
    {
        return $this->uploadStrategy === 'form';
    }

    protected function setUploadRule(string $key, ?string $rule): void
    {
        if ($rule === null) {
            unset($this->uploadValidationRules[$key]);

            return;
        }

        $this->uploadValidationRules[$key] = $rule;
    }

    /** @param string|array<string>|null $accept */
    protected function acceptRule(string|array|null $accept): ?string
    {
        if ($accept === null) {
            return null;
        }

        $values = is_array($accept) ? $accept : array_map('trim', explode(',', $accept));
        $mimeTypes = array_values(array_filter($values, fn (string $value): bool => str_contains($value, '/')));
        $extensions = array_values(array_map(
            fn (string $value): string => ltrim($value, '.'),
            array_filter($values, fn (string $value): bool => ! str_contains($value, '/')),
        ));

        if ($mimeTypes !== []) {
            return 'mimetypes:'.implode(',', $mimeTypes);
        }

        return $extensions === [] ? null : 'mimes:'.implode(',', $extensions);
    }

    protected function guardsUploadTokens(): bool
    {
        if ($this->uploadStrategy === 'form') {
            return false;
        }

        return $this->validatedUploadGuard ?? ! $this->usesCustomTemporaryUrl;
    }

    protected function requiresUploadProfile(): bool
    {
        if (! $this->guardsUploadTokens()) {
            return false;
        }

        return $this->requiresRulesToken
            || $this->configuredRulesToken !== null
            || $this->uploadValidationRules !== []
            || $this->uploadValidator !== null
            || $this->configuredUploadDisk !== null;
    }

    protected function uploadProfile(): UploadRules
    {
        return UploadRules::make(
            array_values($this->uploadValidationRules),
            $this->uploadValidator === null ? [] : [$this->uploadValidator],
            $this->configuredUploadDisk,
        );
    }

    protected function expectedUploadRulesHash(): ?string
    {
        if ($this->configuredRulesToken === null) {
            return $this->uploadProfile()->hash();
        }

        try {
            return UploadRules::fromToken($this->configuredRulesToken)->hash();
        } catch (Throwable) {
            return null;
        }
    }

    /** @param string|array<string>|null $types */
    public function acceptedFileTypes(string|array|null $types): static
    {
        return $this->accept($types);
    }

    public function maxFileSize(?int $kilobytes): static
    {
        return $this->maxSize($kilobytes);
    }

    public function disk(?string $disk): static
    {
        $this->existingFileDisk = $disk;

        return $this->option('disk', $disk);
    }

    public function reorderable(bool $enabled = true): static
    {
        return $this->option('reorderable', $enabled);
    }

    public function keepTokenized(bool $enabled = true): static
    {
        return $this->option('keepTokenized', $enabled);
    }

    public function hasMediaCollection(): bool
    {
        return $this->configuredMediaCollection !== null;
    }

    public function normalizeBoundValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $this->emptyValue();
        }

        if ($this->isMultiple()) {
            $values = is_iterable($value) ? $value : [$value];
            $normalized = [];

            foreach ($values as $item) {
                $normalized[] = $this->normalizeExistingFile($item);
            }

            return $normalized;
        }

        return $this->normalizeExistingFile($value);
    }

    public function resolveMediaCollection(Model $model): mixed
    {
        if ($this->configuredMediaCollection === null || ! method_exists($model, 'getMedia')) {
            return $this->emptyValue();
        }

        $media = $model->getMedia($this->configuredMediaCollection);
        $existing = ExistingFile::fromMediaLibrary($media, withPreview: false);

        if ($this->isMultiple()) {
            $items = is_array($existing) ? $existing : [$existing];

            return array_map(
                static fn (ExistingFile $file): array => $file->toArray(),
                $items,
            );
        }

        if (is_array($existing)) {
            $existing = $existing[0] ?? null;
        }

        return $existing instanceof ExistingFile ? $existing->toArray() : null;
    }

    protected function normalizeExistingFile(mixed $value): mixed
    {
        if ($value instanceof ExistingFile) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            return ExistingFile::fromDisk($this->existingFileDisk ?? 'public', $value, withPreview: false)->toArray();
        }

        return $value;
    }
}
