<?php

declare(strict_types=1);

namespace Inertify\Form\Uploads;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Str;
use Inertify\Form\Contracts\ValidatesFileUploads;
use Inertify\Form\Uploads\Exceptions\InvalidUploadToken;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

final class UploadManager
{
    public function __construct(
        private readonly FilesystemManager $filesystems,
        private readonly UploadToken $tokens,
        private readonly ValidationFactory $validator,
        private readonly Container $container,
        private readonly S3MultipartStorage $s3Multipart,
    ) {}

    public function store(
        HttpUploadedFile $file,
        ?UploadRules $rules = null,
        ?Request $request = null,
    ): TemporaryUpload {
        $this->validateFile($file, $rules);

        $identifier = Str::uuid()->toString();
        $directory = 'inertia-forms-upload-'.$identifier;
        $extension = $file->getClientOriginalExtension();
        $storedName = 'upload'.($extension === '' ? '' : '.'.$extension);
        $path = $this->temporaryDisk()->putFileAs($directory, $file, $storedName);

        if (! is_string($path)) {
            throw new RuntimeException('The temporary upload could not be stored.');
        }

        $upload = new TemporaryUpload(
            $identifier,
            $this->temporaryDiskName(),
            $path,
            $file->getClientOriginalName(),
            $file->getMimeType(),
            $this->temporaryDisk()->size($path),
            'temporary',
            $rules?->hash(),
            now()->getTimestamp(),
        );

        $this->writeMetadata($directory, [
            ...$upload->toArray(),
            'directory' => $directory,
            'state' => 'validating',
        ]);

        try {
            $this->runCustomValidators($upload, $file, $rules, $request ?? request());
            $upload = new TemporaryUpload(
                $upload->getIdentifier(),
                $upload->getDisk(),
                $upload->getPath(),
                $upload->getName(),
                $upload->getMimeType(),
                $upload->getSize(),
                $upload->getKind(),
                $upload->getRulesHash(),
                $upload->getCreatedAt(),
                $this->contentHash($this->temporaryDisk(), $path),
            );
            $this->writeMetadata($directory, [
                ...$upload->toArray(),
                'directory' => $directory,
                'state' => 'completed',
            ]);
        } catch (Throwable $exception) {
            $this->temporaryDisk()->deleteDirectory($directory);

            throw $exception;
        }

        return $upload;
    }

    /**
     * @return array{key: string, name: string, mimeType: string|null, mime_type: string|null, size: int}
     */
    public function response(TemporaryUpload $upload): array
    {
        return [
            'key' => $this->tokenFor($upload),
            'name' => $upload->getName(),
            'mimeType' => $upload->getMimeType(),
            'mime_type' => $upload->getMimeType(),
            'size' => $upload->getSize(),
        ];
    }

    public function tokenFor(TemporaryUpload $upload): string
    {
        return $this->tokens->encode(
            'temporary-upload',
            $upload->toArray(),
            now()->addSeconds($this->lifetime())->getTimestamp(),
        );
    }

    public function resolve(string $token): SubmittedUpload
    {
        $payload = $this->tokens->decode($token);
        $purpose = $payload['_purpose'] ?? null;

        if (! in_array($purpose, ['temporary-upload', 'existing-upload'], true)) {
            throw InvalidUploadToken::unexpectedPurpose();
        }

        if ($purpose === 'temporary-upload') {
            $this->assertFinalizedPayload($payload);
        }

        return new SubmittedUpload($this, $token, $payload);
    }

    public function delete(string $token): bool
    {
        $payload = $this->tokens->decode($token);

        if (($payload['_purpose'] ?? null) !== 'temporary-upload') {
            return false;
        }

        return $this->deletePayload($payload);
    }

    /** @param array<string, mixed> $payload */
    public function materialize(array $payload): UploadedFile
    {
        if (isset($payload['content_hash'])) {
            $this->assertFinalizedPayload($payload);
        }

        $disk = $this->diskForPayload($payload);
        $path = (string) $payload['path'];
        $localPath = $disk->path($path);

        if (! is_file($localPath)) {
            $readStream = $disk->readStream($path);

            if (! is_resource($readStream)) {
                throw new RuntimeException('The temporary upload could not be read.');
            }

            /** @var FilesystemAdapter $materializedDisk */
            $materializedDisk = $this->filesystems->build([
                'driver' => 'local',
                'root' => storage_path('inertia-forms-materialized-uploads'),
                'throw' => true,
            ]);
            $temporaryPath = Str::uuid()->toString();

            try {
                $stored = $materializedDisk->writeStream($temporaryPath, $readStream);
            } finally {
                fclose($readStream);
            }

            if (! $stored) {
                throw new RuntimeException('The local temporary file could not be written.');
            }

            $localPath = $materializedDisk->path($temporaryPath);
            $cleanup = static function () use ($materializedDisk, $temporaryPath): void {
                $materializedDisk->delete($temporaryPath);
            };

            app()->terminating($cleanup);
            register_shutdown_function($cleanup);
        }

        return UploadedFile::fromPath(
            $localPath,
            (string) $payload['name'],
            isset($payload['mime_type']) ? (string) $payload['mime_type'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|string  $options
     */
    public function promote(
        array $payload,
        string $path,
        string $name,
        array|string $options = [],
        bool $deleteTemporary = true,
    ): string {
        $this->assertFinalizedPayload($payload);

        $source = $this->diskForPayload($payload);
        [$destinationDiskName, $writeOptions] = $this->parseOptions($options);
        /** @var FilesystemAdapter $destination */
        $destination = $this->filesystems->disk($destinationDiskName);
        $target = trim($path, '/');
        $target = ($target === '' ? '' : $target.'/').ltrim($name, '/');
        $kind = (string) ($payload['kind'] ?? 'temporary');
        $sourceDiskName = isset($payload['disk']) ? (string) $payload['disk'] : null;

        if ($kind === 'direct' && $sourceDiskName !== $destinationDiskName) {
            throw new LogicException('Direct uploads may only be promoted on the same filesystem disk.');
        }

        if ($kind === 'direct') {
            $stored = $deleteTemporary
                ? $source->move((string) $payload['path'], $target)
                : $source->copy((string) $payload['path'], $target);
        } else {
            $readStream = $source->readStream((string) $payload['path']);

            if (! is_resource($readStream)) {
                throw new RuntimeException('The temporary upload could not be read.');
            }

            $stored = $destination->writeStream($target, $readStream, $writeOptions);
            fclose($readStream);

            if ($stored && $deleteTemporary) {
                $this->deletePayload($payload);
            }
        }

        if (! $stored) {
            throw new RuntimeException('The upload could not be stored.');
        }

        return $target;
    }

    /**
     * @return array{uploadId: string, offset: int, chunkSize: int}
     */
    public function startChunk(string $name, int $size, ?string $mimeType, ?UploadRules $rules = null): array
    {
        $maxSize = $this->configInt('file_uploads.temporary_uploads.chunked.max_size', 2 * 1024 * 1024) * 1024;

        if ($size < 1 || $size > $maxSize) {
            throw new RuntimeException('The declared upload size is invalid.');
        }

        $identifier = Str::uuid()->toString();
        $directory = 'inertia-forms-upload-'.$identifier;
        $metadata = [
            'identifier' => $identifier,
            'directory' => $directory,
            'name' => basename($name),
            'mime_type' => $mimeType,
            'size' => $size,
            'offset' => 0,
            'next_part' => 0,
            'state' => 'pending',
            'rules' => $rules?->toArray(),
            'created_at' => now()->getTimestamp(),
        ];
        $this->writeMetadata($directory, $metadata);

        return [
            'uploadId' => $this->tokens->encode(
                'pending-chunk-upload',
                ['directory' => $directory],
                now()->addSeconds($this->lifetime())->getTimestamp(),
            ),
            'offset' => 0,
            'chunkSize' => $this->chunkSize(),
        ];
    }

    /** @return array{uploadId: string, offset: int, size: int} */
    public function chunkStatus(string $uploadId): array
    {
        $metadata = $this->pendingMetadata($uploadId, 'pending-chunk-upload');

        return [
            'uploadId' => $uploadId,
            'offset' => (int) $metadata['offset'],
            'size' => (int) $metadata['size'],
        ];
    }

    /** @return array{uploadId: string, offset: int, size: int} */
    public function appendChunk(string $uploadId, int $offset, HttpUploadedFile $chunk): array
    {
        $metadata = $this->pendingMetadata($uploadId, 'pending-chunk-upload');

        if ((int) $metadata['offset'] !== $offset) {
            throw new RuntimeException('The chunk offset does not match the next expected byte.');
        }

        $chunkSize = (int) $chunk->getSize();

        if ($chunkSize < 1 || $chunkSize > $this->chunkSize()) {
            throw new RuntimeException('The uploaded chunk size is invalid.');
        }

        if ($offset + $chunkSize > (int) $metadata['size']) {
            throw new RuntimeException('The uploaded chunk exceeds the declared file size.');
        }

        $directory = (string) $metadata['directory'];
        $part = (int) $metadata['next_part'];
        $stored = $this->temporaryDisk()->putFileAs($directory.'/chunks', $chunk, (string) $part);

        if (! is_string($stored)) {
            throw new RuntimeException('The chunk could not be stored.');
        }

        $metadata['offset'] = $offset + $chunkSize;
        $metadata['next_part'] = $part + 1;
        $this->writeMetadata($directory, $metadata);

        return [
            'uploadId' => $uploadId,
            'offset' => (int) $metadata['offset'],
            'size' => (int) $metadata['size'],
        ];
    }

    public function completeChunk(string $uploadId, ?Request $request = null): TemporaryUpload
    {
        $metadata = $this->pendingMetadata($uploadId, 'pending-chunk-upload');

        if ((int) $metadata['offset'] !== (int) $metadata['size']) {
            throw new RuntimeException('The chunked upload is incomplete.');
        }

        $metadata['state'] = 'completing';
        $this->writeMetadata((string) $metadata['directory'], $metadata);

        $directory = (string) $metadata['directory'];
        $target = $directory.'/upload';
        $stream = tmpfile();

        if (! is_resource($stream)) {
            throw new RuntimeException('The completed upload stream could not be created.');
        }

        for ($part = 0; $part < (int) $metadata['next_part']; $part++) {
            $chunk = $this->temporaryDisk()->readStream($directory.'/chunks/'.$part);

            if (! is_resource($chunk)) {
                fclose($stream);

                throw new RuntimeException('One or more upload chunks are missing.');
            }

            stream_copy_to_stream($chunk, $stream);
            fclose($chunk);
        }

        rewind($stream);
        $stored = $this->temporaryDisk()->writeStream($target, $stream);
        fclose($stream);

        if (! $stored) {
            throw new RuntimeException('The chunked upload could not be completed.');
        }

        $this->temporaryDisk()->deleteDirectory($directory.'/chunks');
        $rules = $this->rulesFromMetadata($metadata);

        try {
            $upload = new TemporaryUpload(
                (string) $metadata['identifier'],
                $this->temporaryDiskName(),
                $target,
                (string) $metadata['name'],
                isset($metadata['mime_type']) ? (string) $metadata['mime_type'] : null,
                (int) $metadata['size'],
                'temporary',
                $rules?->hash(),
                (int) $metadata['created_at'],
            );
            $file = $this->materialize($upload->toArray());
            $this->validateFile(
                $file,
                $rules,
                $this->configInt('file_uploads.temporary_uploads.chunked.max_size', 2 * 1024 * 1024),
            );
            $this->runCustomValidators($upload, $file, $rules, $request ?? request());
            $upload = new TemporaryUpload(
                $upload->getIdentifier(),
                $upload->getDisk(),
                $upload->getPath(),
                $upload->getName(),
                $upload->getMimeType(),
                $upload->getSize(),
                $upload->getKind(),
                $upload->getRulesHash(),
                $upload->getCreatedAt(),
                $this->contentHash($this->temporaryDisk(), $target),
            );
            $this->writeMetadata($directory, [
                ...$upload->toArray(),
                'directory' => $directory,
                'state' => 'completed',
                'completed_at' => now()->getTimestamp(),
            ]);
        } catch (Throwable $exception) {
            $this->temporaryDisk()->deleteDirectory($directory);

            throw $exception;
        }

        return $upload;
    }

    public function abortChunk(string $uploadId): bool
    {
        $metadata = $this->pendingMetadata($uploadId, 'pending-chunk-upload');

        return $this->temporaryDisk()->deleteDirectory((string) $metadata['directory']);
    }

    /**
     * @return array{uploadId: string, mode: 'single'|'multipart', uploadUrl: string|null, headers: array<string, string>, partSize: int}
     */
    public function startDirect(
        string $name,
        int $size,
        ?string $mimeType,
        ?string $disk = null,
        ?UploadRules $rules = null,
    ): array {
        $allowedDisk = $rules?->disk() ?? $this->directDiskName();

        if ($disk !== null && $disk !== '' && $disk !== $allowedDisk) {
            throw new RuntimeException('The direct upload disk does not match the upload profile.');
        }

        $disk = $allowedDisk;

        if ($disk === null || $disk === '') {
            throw new RuntimeException('A direct upload filesystem disk is required.');
        }

        $maxSize = $this->configInt('file_uploads.direct_to_storage.max_size', 5 * 1024 * 1024) * 1024;

        if ($size < 1 || $size > $maxSize) {
            throw new RuntimeException('The declared upload size is invalid.');
        }

        if ($this->partSize() < 1) {
            throw new RuntimeException('The direct upload part size must be greater than zero.');
        }

        $identifier = Str::uuid()->toString();
        $directory = 'inertia-forms-upload-'.$identifier;
        $finalPath = $directory.'/'.self::safeFilename($name);
        $path = $directory.'/.pending-'.self::safeFilename($name);
        $mode = $size > $this->multipartThreshold() ? 'multipart' : 'single';
        $directDisk = $this->disk($disk);
        $metadata = [
            'identifier' => $identifier,
            'directory' => $directory,
            'disk' => $disk,
            'path' => $path,
            'final_path' => $finalPath,
            'name' => basename($name),
            'mime_type' => $mimeType,
            'size' => $size,
            'mode' => $mode,
            'state' => 'pending',
            'rules' => $rules?->toArray(),
            'created_at' => now()->getTimestamp(),
        ];

        if ($mode === 'multipart' && $this->s3Multipart->supports($directDisk)) {
            $partCount = (int) ceil($size / $this->partSize());

            if ($partCount > 1 && $this->partSize() < 5 * 1024 * 1024) {
                throw new RuntimeException('S3 multipart upload parts must be at least 5 MiB.');
            }

            if ($partCount > 10_000) {
                throw new RuntimeException('S3 multipart uploads may not exceed 10,000 parts.');
            }

            $metadata['multipart_driver'] = 's3';
            $metadata['provider_upload_id'] = $this->s3Multipart->start($directDisk, $path, $mimeType);
        }

        try {
            $uploadId = $this->tokens->encode(
                'pending-direct-upload',
                $metadata,
                now()->addSeconds($this->directUrlLifetime())->getTimestamp(),
            );
            $this->writeDirectMetadata($metadata);
        } catch (Throwable $exception) {
            if (($metadata['multipart_driver'] ?? null) === 's3') {
                try {
                    $this->s3Multipart->abort(
                        $directDisk,
                        $path,
                        (string) $metadata['provider_upload_id'],
                    );
                } catch (Throwable) {
                    // Preserve the failure that prevented issuing a usable token.
                }
            }

            try {
                $directDisk->deleteDirectory($directory);
            } catch (Throwable) {
                // Preserve the failure that prevented issuing a usable token.
            }

            throw $exception;
        }

        return [
            'uploadId' => $uploadId,
            'mode' => $mode,
            'uploadUrl' => null,
            'headers' => [],
            'partSize' => $this->partSize(),
        ];
    }

    /** @return array<string, mixed> */
    public function directMetadata(string $uploadId): array
    {
        $payload = $this->tokens->decode($uploadId, 'pending-direct-upload');
        $diskName = $payload['disk'] ?? null;
        $directory = $payload['directory'] ?? null;

        if (! is_string($diskName) || $diskName === '' || ! is_string($directory) || $directory === '') {
            throw InvalidUploadToken::malformed();
        }

        $metadata = $this->readMetadata($this->disk($diskName), $directory);
        $this->ensureOwnedMetadata($metadata, $directory);
        $this->ensureDirectMetadataMatchesToken($metadata, $payload);

        return $metadata;
    }

    public function putDirectObject(string $uploadId, mixed $stream, ?int $partNumber = null): void
    {
        if (! is_resource($stream)) {
            throw new InvalidArgumentException('Direct upload content must be a readable stream resource.');
        }

        $metadata = $this->pendingDirectMetadata($uploadId);
        $disk = $this->disk((string) $metadata['disk']);

        if ($this->usesS3Multipart($metadata, $disk)) {
            throw new RuntimeException('S3 multipart bytes must be sent to a signed upload-part URL.');
        }

        $multipart = ($metadata['mode'] ?? null) === 'multipart';

        if (($multipart && $partNumber === null) || (! $multipart && $partNumber !== null)) {
            throw new RuntimeException('The direct upload part does not match the upload mode.');
        }

        if ($partNumber !== null && ($partNumber < 1 || $partNumber > $this->directPartCount($metadata))) {
            throw new RuntimeException('The direct upload part number is outside the declared upload size.');
        }

        $path = $partNumber === null
            ? (string) $metadata['path']
            : (string) $metadata['directory'].'/parts/'.$partNumber;

        if (! $disk->writeStream($path, $stream)) {
            throw new RuntimeException('The direct upload bytes could not be stored.');
        }

        $expectedSize = $partNumber === null
            ? (int) $metadata['size']
            : $this->expectedDirectPartSize($metadata, $partNumber);

        if ($disk->size($path) !== $expectedSize) {
            $disk->delete($path);

            throw new RuntimeException('The direct upload part size does not match the declared upload.');
        }
    }

    /** @return array{url: string, headers: array<string, string>, partNumber: int}|null */
    public function signDirectPart(string $uploadId, int $partNumber): ?array
    {
        $metadata = $this->pendingDirectMetadata($uploadId);
        $disk = $this->disk((string) $metadata['disk']);
        $maximumPart = $this->directPartCount($metadata);

        if ($partNumber < 1 || $partNumber > $maximumPart || $partNumber > 10_000) {
            throw new RuntimeException('The direct upload part number is outside the declared upload size.');
        }

        if (! $this->usesS3Multipart($metadata, $disk)) {
            return null;
        }

        return $this->s3Multipart->signPart(
            $disk,
            (string) $metadata['path'],
            (string) $metadata['provider_upload_id'],
            $partNumber,
            now()->addSeconds($this->directUrlLifetime()),
        );
    }

    /** @return array{uploadId: string, parts: list<array{partNumber: int, size: int, etag?: string}>} */
    public function directStatus(string $uploadId): array
    {
        $metadata = $this->pendingDirectMetadata($uploadId);
        $disk = $this->disk((string) $metadata['disk']);

        return ['uploadId' => $uploadId, 'parts' => $this->directParts($metadata, $disk)];
    }

    /** @param list<array{partNumber: int, etag: string}> $submittedParts */
    public function completeDirect(
        string $uploadId,
        array $submittedParts = [],
        ?Request $request = null,
    ): TemporaryUpload {
        $metadata = $this->pendingDirectMetadata($uploadId);
        $disk = $this->disk((string) $metadata['disk']);
        $usesS3Multipart = $this->usesS3Multipart($metadata, $disk);
        $metadata['state'] = 'completing';
        $this->writeDirectMetadata($metadata);

        try {
            if (($metadata['mode'] ?? null) === 'multipart') {
                if ($usesS3Multipart) {
                    $listedParts = $this->s3Multipart->status(
                        $disk,
                        (string) $metadata['path'],
                        (string) $metadata['provider_upload_id'],
                    );
                    $completionParts = $this->validatedS3CompletionParts(
                        $listedParts,
                        $submittedParts,
                        (int) $metadata['size'],
                    );
                    $this->s3Multipart->complete(
                        $disk,
                        (string) $metadata['path'],
                        (string) $metadata['provider_upload_id'],
                        $completionParts,
                    );
                } else {
                    $stream = tmpfile();

                    if (! is_resource($stream)) {
                        throw new RuntimeException('The multipart upload stream could not be created.');
                    }

                    $parts = $this->directParts($metadata, $disk);
                    $this->validateLocalMultipartParts($metadata, $parts);

                    foreach ($parts as $part) {
                        $partStream = $disk->readStream((string) $metadata['directory'].'/parts/'.$part['partNumber']);

                        if (! is_resource($partStream)) {
                            fclose($stream);

                            throw new RuntimeException('One or more direct upload parts are missing.');
                        }

                        stream_copy_to_stream($partStream, $stream);
                        fclose($partStream);
                    }

                    rewind($stream);
                    $stored = $disk->writeStream((string) $metadata['path'], $stream);
                    fclose($stream);

                    if (! $stored) {
                        throw new RuntimeException('The multipart upload could not be assembled.');
                    }

                    $disk->deleteDirectory((string) $metadata['directory'].'/parts');
                }
            }

            $pendingPath = (string) $metadata['path'];
            $finalPath = (string) $metadata['final_path'];

            if (! $disk->exists($pendingPath)
                || $disk->size($pendingPath) !== (int) $metadata['size']) {
                throw new RuntimeException('The completed direct upload size does not match the declared size.');
            }

            if (! $disk->move($pendingPath, $finalPath)) {
                throw new RuntimeException('The completed direct upload could not be finalized.');
            }

            $rules = $this->rulesFromMetadata($metadata);
            $upload = new TemporaryUpload(
                (string) $metadata['identifier'],
                (string) $metadata['disk'],
                $finalPath,
                (string) $metadata['name'],
                isset($metadata['mime_type']) ? (string) $metadata['mime_type'] : null,
                (int) $metadata['size'],
                'direct',
                $rules?->hash(),
                (int) $metadata['created_at'],
            );

            if ($rules !== null && ($rules->rules() !== [] || $rules->validators() !== [])) {
                $file = $this->materialize($upload->toArray());
                $this->validateFile(
                    $file,
                    $rules,
                    $this->configInt('file_uploads.direct_to_storage.max_size', 5 * 1024 * 1024),
                );
                $this->runCustomValidators($upload, $file, $rules, $request ?? request());
            }

            $contentHash = $this->contentHash($disk, $finalPath);
            $upload = new TemporaryUpload(
                $upload->getIdentifier(),
                $upload->getDisk(),
                $upload->getPath(),
                $upload->getName(),
                $upload->getMimeType(),
                $upload->getSize(),
                $upload->getKind(),
                $upload->getRulesHash(),
                $upload->getCreatedAt(),
                $contentHash,
            );
            $metadata['state'] = 'completed';
            $metadata['completed_at'] = now()->getTimestamp();
            $metadata['content_hash'] = $contentHash;
            $this->writeDirectMetadata($metadata);
        } catch (Throwable $exception) {
            try {
                $disk->deleteDirectory((string) $metadata['directory']);
            } catch (Throwable) {
                // Preserve the completion or validation failure for the caller.
            }

            throw $exception;
        }

        return $upload;
    }

    public function abortDirect(string $uploadId): bool
    {
        $metadata = $this->pendingDirectMetadata($uploadId);
        $disk = $this->disk((string) $metadata['disk']);

        if ($this->usesS3Multipart($metadata, $disk)) {
            $this->s3Multipart->abort(
                $disk,
                (string) $metadata['path'],
                (string) $metadata['provider_upload_id'],
            );
        }

        return $disk->deleteDirectory((string) $metadata['directory']);
    }

    public function cleanup(?int $lifetime = null): int
    {
        return $this->cleanupReport($lifetime)['removed'];
    }

    /**
     * Cleanup continues after individual failures. Failed directories are left
     * intact so a later command invocation can safely retry them.
     *
     * @return array{removed: int, failed: int, errors: list<string>}
     */
    public function cleanupReport(?int $lifetime = null): array
    {
        $report = ['removed' => 0, 'failed' => 0, 'errors' => []];
        $temporaryLifetime = max(0, $lifetime ?? $this->lifetime());
        $pendingDirectLifetime = max(0, $lifetime ?? $this->directUrlLifetime());

        $this->cleanupTemporaryDirectories(
            $this->temporaryDisk(),
            $this->temporaryDiskName() ?? 'temporary',
            $temporaryLifetime,
            $report,
        );

        $directDiskName = $this->directDiskName();

        if ($directDiskName !== null) {
            try {
                $this->cleanupDirectDirectories(
                    $this->disk($directDiskName),
                    $directDiskName,
                    $pendingDirectLifetime,
                    $this->lifetime(),
                    $report,
                );
            } catch (Throwable $exception) {
                $this->recordCleanupFailure($report, $directDiskName, '*', $exception);
            }
        }

        return $report;
    }

    /**
     * @param  array{removed: int, failed: int, errors: list<string>}  $report
     */
    private function cleanupTemporaryDirectories(
        FilesystemAdapter $disk,
        string $diskName,
        int $lifetime,
        array &$report,
    ): void {
        try {
            $directories = $disk->directories();
        } catch (Throwable $exception) {
            $this->recordCleanupFailure($report, $diskName, '*', $exception);

            return;
        }

        $threshold = now()->getTimestamp() - $lifetime;

        foreach ($directories as $directory) {
            if (! $this->isPackageUploadDirectory($directory)) {
                continue;
            }

            try {
                $metadata = $this->readMetadata($disk, $directory);

                if (array_key_exists('mode', $metadata)) {
                    continue;
                }

                $this->ensureOwnedMetadata($metadata, $directory);

                if ($this->cleanupTimestamp($metadata, 'created_at') > $threshold) {
                    continue;
                }

                if (! $disk->deleteDirectory($directory)) {
                    throw new RuntimeException('The upload directory could not be deleted.');
                }

                $report['removed']++;
            } catch (Throwable $exception) {
                $this->recordCleanupFailure($report, $diskName, $directory, $exception);
            }
        }
    }

    /**
     * @param  array{removed: int, failed: int, errors: list<string>}  $report
     */
    private function cleanupDirectDirectories(
        FilesystemAdapter $disk,
        string $diskName,
        int $pendingLifetime,
        int $completedLifetime,
        array &$report,
    ): void {
        $directories = $disk->directories();
        $now = now()->getTimestamp();

        foreach ($directories as $directory) {
            if (! $this->isPackageUploadDirectory($directory)) {
                continue;
            }

            try {
                $metadata = $this->readMetadata($disk, $directory);

                if (! array_key_exists('mode', $metadata)) {
                    continue;
                }

                $this->ensureOwnedMetadata($metadata, $directory);
                $this->ensureDirectCleanupMetadata($metadata, $diskName);

                $state = is_string($metadata['state'] ?? null) ? $metadata['state'] : 'pending';
                $path = $state === 'completed'
                    ? (string) ($metadata['final_path'] ?? '')
                    : (string) $metadata['path'];
                $pendingS3Multipart = $state === 'pending'
                    && ($metadata['mode'] ?? null) === 'multipart'
                    && ($metadata['multipart_driver'] ?? null) === 's3';
                $looksCompleted = $state === 'completed'
                    || ($state === 'pending' && ! $pendingS3Multipart && $disk->exists($path));
                $timestamp = $looksCompleted
                    ? $this->cleanupTimestamp($metadata, isset($metadata['completed_at']) ? 'completed_at' : 'created_at')
                    : $this->cleanupTimestamp($metadata, 'created_at');
                $effectiveLifetime = $looksCompleted ? max(0, $completedLifetime) : $pendingLifetime;

                if ($timestamp > $now - $effectiveLifetime) {
                    continue;
                }

                if (! $looksCompleted
                    && $state !== 'aborted'
                    && ($metadata['mode'] ?? null) === 'multipart'
                    && ($metadata['multipart_driver'] ?? null) === 's3') {
                    $this->s3Multipart->abort(
                        $disk,
                        $path,
                        (string) $metadata['provider_upload_id'],
                    );
                    $metadata['state'] = 'aborted';
                    $metadata['aborted_at'] = $now;
                    $this->writeDirectMetadata($metadata);
                }

                if (! $disk->deleteDirectory($directory)) {
                    throw new RuntimeException('The direct upload directory could not be deleted.');
                }

                $report['removed']++;
            } catch (Throwable $exception) {
                $this->recordCleanupFailure($report, $diskName, $directory, $exception);
            }
        }
    }

    private function isPackageUploadDirectory(string $directory): bool
    {
        return preg_match(
            '/\Ainertia-forms-upload-[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i',
            $directory,
        ) === 1;
    }

    /** @param array<string, mixed> $metadata */
    private function ensureOwnedMetadata(array $metadata, string $directory): void
    {
        $identifier = $metadata['identifier'] ?? null;
        $metadataDirectory = $metadata['directory'] ?? null;
        $path = $metadata['path'] ?? null;
        $finalPath = $metadata['final_path'] ?? null;
        $expectedIdentifier = substr($directory, strlen('inertia-forms-upload-'));

        if (! is_string($identifier)
            || ! hash_equals($expectedIdentifier, $identifier)
            || ($metadataDirectory !== null && $metadataDirectory !== $directory)
            || ($path !== null && (! is_string($path) || ! str_starts_with($path, $directory.'/')))
            || ($finalPath !== null && (! is_string($finalPath) || ! str_starts_with($finalPath, $directory.'/')))) {
            throw new RuntimeException('The upload metadata does not own this package directory.');
        }

        if ($metadataDirectory === null && $path === null) {
            throw new RuntimeException('The upload metadata has no package-owned path.');
        }
    }

    /** @param array<string, mixed> $metadata */
    private function ensureDirectCleanupMetadata(array $metadata, string $diskName): void
    {
        $mode = $metadata['mode'] ?? null;
        $state = $metadata['state'] ?? 'pending';

        if (! in_array($mode, ['single', 'multipart'], true)
            || ! in_array($state, ['pending', 'completing', 'completed', 'aborted'], true)
            || ($metadata['disk'] ?? null) !== $diskName) {
            throw new RuntimeException('The direct upload metadata is invalid.');
        }

        if (($metadata['multipart_driver'] ?? null) === 's3'
            && (! is_string($metadata['provider_upload_id'] ?? null)
                || $metadata['provider_upload_id'] === '')) {
            throw new RuntimeException('The S3 multipart upload metadata is invalid.');
        }
    }

    /** @param array<string, mixed> $metadata */
    private function cleanupTimestamp(array $metadata, string $key): int
    {
        $timestamp = $metadata[$key] ?? null;

        if (! is_int($timestamp) || $timestamp < 1) {
            throw new RuntimeException('The upload cleanup timestamp is invalid.');
        }

        return $timestamp;
    }

    /**
     * @param  array{removed: int, failed: int, errors: list<string>}  $report
     */
    private function recordCleanupFailure(
        array &$report,
        string $disk,
        string $directory,
        Throwable $exception,
    ): void {
        $message = sprintf('%s:%s — %s', $disk, $directory, $exception->getMessage());

        if (in_array($message, $report['errors'], true)) {
            return;
        }

        $report['failed']++;
        $report['errors'][] = $message;
    }

    public function temporaryDisk(): FilesystemAdapter
    {
        $name = $this->temporaryDiskName();

        if ($name !== null && $name !== '') {
            return $this->disk($name);
        }

        /** @var FilesystemAdapter $disk */
        $disk = $this->filesystems->build([
            'driver' => 'local',
            'root' => storage_path('inertia-forms-temporary-uploads'),
            'throw' => true,
        ]);

        return $disk;
    }

    public function temporaryDiskName(): ?string
    {
        $disk = config('inertia-forms.file_uploads.temporary_uploads.disk');

        return is_string($disk) && $disk !== '' ? $disk : null;
    }

    public function directDiskName(): ?string
    {
        $disk = config('inertia-forms.file_uploads.direct_to_storage.disk');

        return is_string($disk) && $disk !== '' ? $disk : null;
    }

    public function chunkSize(): int
    {
        return $this->configInt('file_uploads.temporary_uploads.chunked.size', 5 * 1024 * 1024);
    }

    public function partSize(): int
    {
        return $this->configInt('file_uploads.direct_to_storage.part_size', 16 * 1024 * 1024);
    }

    public function multipartThreshold(): int
    {
        return $this->configInt('file_uploads.direct_to_storage.multipart_threshold', 100 * 1024 * 1024);
    }

    public function lifetime(): int
    {
        return $this->configInt('file_uploads.temporary_uploads.lifetime', 3600);
    }

    public function directUrlLifetime(): int
    {
        return $this->configInt('file_uploads.direct_to_storage.url_lifetime', 900);
    }

    /** @param array<string, mixed> $payload */
    private function diskForPayload(array $payload): FilesystemAdapter
    {
        $name = $payload['disk'] ?? null;

        return is_string($name) && $name !== '' ? $this->disk($name) : $this->temporaryDisk();
    }

    private function disk(string $name): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = $this->filesystems->disk($name);

        return $disk;
    }

    /** @param array<string, mixed> $metadata */
    private function usesS3Multipart(array $metadata, FilesystemAdapter $disk): bool
    {
        if (($metadata['multipart_driver'] ?? null) !== 's3') {
            return false;
        }

        if (! is_string($metadata['provider_upload_id'] ?? null)
            || $metadata['provider_upload_id'] === ''
            || ! $this->s3Multipart->supports($disk)) {
            throw new RuntimeException('The S3 multipart upload adapter is no longer available.');
        }

        return true;
    }

    /** @param array<string, mixed> $metadata */
    private function directPartCount(array $metadata): int
    {
        return (int) ceil((int) $metadata['size'] / $this->partSize());
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return list<array{partNumber: int, size: int, etag?: string}>
     */
    private function directParts(array $metadata, FilesystemAdapter $disk): array
    {
        if ($this->usesS3Multipart($metadata, $disk)) {
            return $this->s3Multipart->status(
                $disk,
                (string) $metadata['path'],
                (string) $metadata['provider_upload_id'],
            );
        }

        $parts = [];

        foreach ($disk->files((string) $metadata['directory'].'/parts') as $path) {
            $partNumber = (int) basename($path);
            $parts[] = ['partNumber' => $partNumber, 'size' => $disk->size($path)];
        }

        usort($parts, fn (array $left, array $right): int => $left['partNumber'] <=> $right['partNumber']);

        return $parts;
    }

    /** @param array<string, mixed> $metadata */
    private function expectedDirectPartSize(array $metadata, int $partNumber): int
    {
        $remaining = (int) $metadata['size'] - (($partNumber - 1) * $this->partSize());

        return min($this->partSize(), max(0, $remaining));
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<array{partNumber: int, size: int, etag?: string}>  $parts
     */
    private function validateLocalMultipartParts(array $metadata, array $parts): void
    {
        if (count($parts) !== $this->directPartCount($metadata)) {
            throw new RuntimeException('The direct multipart upload part list is incomplete.');
        }

        foreach ($parts as $index => $part) {
            $partNumber = $index + 1;

            if ($part['partNumber'] !== $partNumber
                || $part['size'] !== $this->expectedDirectPartSize($metadata, $partNumber)) {
                throw new RuntimeException('A direct multipart upload part has an invalid size or order.');
            }
        }
    }

    /**
     * @param  list<array{partNumber: int, size: int, etag: string}>  $listedParts
     * @param  list<array{partNumber: int, etag: string}>  $submittedParts
     * @return list<array{partNumber: int, etag: string}>
     */
    private function validatedS3CompletionParts(
        array $listedParts,
        array $submittedParts,
        int $declaredSize,
    ): array {
        if ($listedParts === []) {
            throw new RuntimeException('The S3 multipart upload has no completed parts.');
        }

        /** @var array<int, array{partNumber: int, size: int, etag: string}> $listedByNumber */
        $listedByNumber = [];

        foreach ($listedParts as $part) {
            $listedByNumber[$part['partNumber']] = $part;
        }

        if ($submittedParts === []) {
            $submittedParts = array_map(static fn (array $part): array => [
                'partNumber' => $part['partNumber'],
                'etag' => $part['etag'],
            ], $listedParts);
        }

        usort($submittedParts, fn (array $left, array $right): int => $left['partNumber'] <=> $right['partNumber']);

        if (count($submittedParts) !== (int) ceil($declaredSize / $this->partSize())) {
            throw new RuntimeException('The S3 multipart upload part list is incomplete.');
        }

        $completionParts = [];
        $completedSize = 0;

        foreach ($submittedParts as $index => $submitted) {
            $partNumber = $submitted['partNumber'];
            $listed = $listedByNumber[$partNumber] ?? null;

            if ($partNumber !== $index + 1 || $listed === null) {
                throw new RuntimeException('The S3 multipart upload part list is incomplete.');
            }

            if (! hash_equals(trim($listed['etag'], '"'), trim($submitted['etag'], '"'))) {
                throw new RuntimeException('An S3 multipart upload ETag does not match the stored part.');
            }

            $expectedSize = min(
                $this->partSize(),
                $declaredSize - (($partNumber - 1) * $this->partSize()),
            );

            if ($listed['size'] !== $expectedSize) {
                throw new RuntimeException('An S3 multipart upload part has an invalid size.');
            }

            $completedSize += $listed['size'];
            $completionParts[] = [
                'partNumber' => $partNumber,
                'etag' => $listed['etag'],
            ];
        }

        if ($completedSize !== $declaredSize) {
            throw new RuntimeException('The completed direct upload size does not match the declared size.');
        }

        return $completionParts;
    }

    /** @param array<string, mixed> $payload */
    private function deletePayload(array $payload): bool
    {
        $path = (string) $payload['path'];
        $directory = dirname($path);

        return $this->diskForPayload($payload)->deleteDirectory($directory);
    }

    /** @param array<string, mixed> $metadata */
    private function writeMetadata(string $directory, array $metadata): void
    {
        $stored = $this->temporaryDisk()->put(
            $directory.'/.metadata.json',
            json_encode($metadata, JSON_THROW_ON_ERROR),
        );

        if (! $stored) {
            throw new RuntimeException('The upload metadata could not be stored.');
        }
    }

    /** @param array<string, mixed> $metadata */
    private function writeDirectMetadata(array $metadata): void
    {
        $stored = $this->disk((string) $metadata['disk'])->put(
            (string) $metadata['directory'].'/.metadata.json',
            json_encode($metadata, JSON_THROW_ON_ERROR),
        );

        if (! $stored) {
            throw new RuntimeException('The direct upload metadata could not be stored.');
        }
    }

    /** @return array<string, mixed> */
    private function pendingMetadata(string $token, string $purpose): array
    {
        $payload = $this->tokens->decode($token, $purpose);
        $directory = $payload['directory'] ?? null;

        if (! is_string($directory) || $directory === '') {
            throw InvalidUploadToken::malformed();
        }

        $metadata = $this->readMetadata($this->temporaryDisk(), $directory);
        $this->ensureOwnedMetadata($metadata, $directory);

        if (($metadata['state'] ?? null) !== 'pending') {
            throw new RuntimeException('The upload session is no longer pending.');
        }

        return $metadata;
    }

    /** @return array<string, mixed> */
    private function pendingDirectMetadata(string $uploadId): array
    {
        $metadata = $this->directMetadata($uploadId);

        if (($metadata['state'] ?? null) !== 'pending') {
            throw new RuntimeException('The direct upload session is no longer pending.');
        }

        return $metadata;
    }

    /** @return array<string, mixed> */
    private function readMetadata(FilesystemAdapter $disk, string $directory): array
    {
        $json = $disk->get($directory.'/.metadata.json');

        if (! is_string($json)) {
            throw InvalidUploadToken::malformed();
        }

        $metadata = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($metadata)) {
            throw InvalidUploadToken::malformed();
        }

        return $metadata;
    }

    private function validateFile(HttpUploadedFile $file, ?UploadRules $rules, ?int $maximumKiB = null): void
    {
        $configuredMax = $maximumKiB
            ?? $this->configInt('file_uploads.temporary_uploads.max_size', 10240);
        $validationRules = ['required', 'file', 'max:'.$configuredMax];

        if ($rules !== null) {
            $validationRules = [...$validationRules, ...$rules->rules()];
        }

        $this->validator->make(['file' => $file], ['file' => $validationRules])->validate();
    }

    private function runCustomValidators(
        TemporaryUpload $upload,
        HttpUploadedFile $file,
        ?UploadRules $rules,
        Request $request,
    ): void {
        if ($rules === null) {
            return;
        }

        foreach ($rules->validators() as $validator) {
            $instance = $this->container->make($validator);

            if (! $instance instanceof ValidatesFileUploads) {
                throw new LogicException($validator.' must implement '.ValidatesFileUploads::class.'.');
            }

            $instance->validate($upload, $file, $rules, $request);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function rulesFromMetadata(array $metadata): ?UploadRules
    {
        $rules = $metadata['rules'] ?? null;

        if (! is_array($rules)) {
            return null;
        }

        $ruleValues = $rules['rules'] ?? [];
        $validatorValues = $rules['validators'] ?? [];
        $rulesDisk = $rules['disk'] ?? null;

        if (! is_array($ruleValues)
            || ! is_array($validatorValues)
            || ($rulesDisk !== null && ! is_string($rulesDisk))) {
            throw InvalidUploadToken::malformed();
        }

        $normalizedRules = array_values(array_filter($ruleValues, is_string(...)));
        $normalizedValidators = array_values(array_filter(
            $validatorValues,
            fn (mixed $validator): bool => is_string($validator) && class_exists($validator),
        ));

        if (count($normalizedRules) !== count($ruleValues)
            || count($normalizedValidators) !== count($validatorValues)) {
            throw InvalidUploadToken::malformed();
        }

        return UploadRules::make(
            $normalizedRules,
            $normalizedValidators,
            $rulesDisk,
        );
    }

    /** @param array<string, mixed>|string $options
     * @return array{string, array<string, mixed>}
     */
    private function parseOptions(array|string $options): array
    {
        if (is_string($options)) {
            return [$options, []];
        }

        $disk = $options['disk'] ?? config('filesystems.default');
        unset($options['disk']);

        if (! is_string($disk) || $disk === '') {
            throw new LogicException('A destination filesystem disk is required.');
        }

        return [$disk, $options];
    }

    private function configInt(string $key, int $default): int
    {
        $value = config('inertia-forms.'.$key, $default);

        return is_int($value) ? $value : (int) $value;
    }

    /** @param array<string, mixed> $metadata
     * @param  array<string, mixed>  $payload
     */
    private function ensureDirectMetadataMatchesToken(array $metadata, array $payload): void
    {
        foreach ([
            'identifier', 'directory', 'disk', 'path', 'final_path', 'name',
            'mime_type', 'size', 'mode', 'rules', 'created_at',
            'multipart_driver', 'provider_upload_id',
        ] as $key) {
            if (($metadata[$key] ?? null) !== ($payload[$key] ?? null)) {
                throw InvalidUploadToken::malformed();
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private function assertFinalizedPayload(array $payload): void
    {
        $path = $payload['path'] ?? null;
        $size = $payload['size'] ?? null;
        $contentHash = $payload['content_hash'] ?? null;

        if (! is_string($path)
            || $path === ''
            || ! is_int($size)
            || $size < 0
            || ! is_string($contentHash)
            || preg_match('/\A[a-f0-9]{64}\z/', $contentHash) !== 1) {
            throw InvalidUploadToken::malformed();
        }

        $disk = $this->diskForPayload($payload);

        if (! $disk->exists($path)
            || $disk->size($path) !== $size
            || ! hash_equals($contentHash, $this->contentHash($disk, $path))) {
            throw InvalidUploadToken::malformed();
        }

        $directory = dirname($path);
        $metadata = $this->readMetadata($disk, $directory);
        $this->ensureOwnedMetadata($metadata, $directory);
        $metadataPath = ($payload['kind'] ?? null) === 'direct'
            ? $metadata['final_path'] ?? null
            : $metadata['path'] ?? null;

        if (($metadata['state'] ?? null) !== 'completed'
            || $metadataPath !== $path
            || ($metadata['identifier'] ?? null) !== ($payload['identifier'] ?? null)
            || ($metadata['disk'] ?? null) !== ($payload['disk'] ?? null)
            || ($metadata['size'] ?? null) !== $size
            || ($metadata['content_hash'] ?? null) !== $contentHash) {
            throw InvalidUploadToken::malformed();
        }
    }

    private function contentHash(FilesystemAdapter $disk, string $path): string
    {
        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('The finalized upload content could not be read.');
        }

        $hash = hash_init('sha256');

        try {
            hash_update_stream($hash, $stream);
        } finally {
            fclose($stream);
        }

        return hash_final($hash);
    }

    private static function safeFilename(string $name): string
    {
        $filename = basename($name);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        return 'upload'.($extension === '' ? '' : '.'.$extension);
    }
}
