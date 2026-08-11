<?php

declare(strict_types=1);

namespace Inertify\Form\Uploads;

use ArrayAccess;
use DateTimeInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use ReflectionMethod;
use RuntimeException;
use Throwable;

/**
 * Runtime-only bridge to an S3-compatible Laravel disk.
 *
 * No AWS SDK types are referenced so applications that do not install Laravel's
 * optional S3 adapter can continue to use temporary and local direct uploads.
 */
final class S3MultipartStorage
{
    public function supports(FilesystemAdapter $disk): bool
    {
        $config = $disk->getConfig();

        if (! is_string($config['bucket'] ?? null) || $config['bucket'] === '') {
            return false;
        }

        if (! method_exists($disk, 'getClient')) {
            return false;
        }

        try {
            $client = $this->client($disk);
        } catch (Throwable) {
            return false;
        }

        return method_exists($client, 'getCommand')
            && method_exists($client, 'execute')
            && method_exists($client, 'createPresignedRequest');
    }

    public function start(FilesystemAdapter $disk, string $path, ?string $mimeType): string
    {
        $arguments = [
            ...$this->createOptions($disk),
            ...$this->objectArguments($disk, $path),
        ];

        if ($mimeType !== null && $mimeType !== '') {
            $arguments['ContentType'] = $mimeType;
        }

        try {
            $result = $this->execute($disk, 'CreateMultipartUpload', $arguments);
            $uploadId = $this->resultValue($result, 'UploadId');
        } catch (Throwable $exception) {
            throw new RuntimeException('The S3 multipart upload could not be started.', 0, $exception);
        }

        if (! is_string($uploadId) || $uploadId === '') {
            throw new RuntimeException('The S3 multipart upload did not return an upload identifier.');
        }

        return $uploadId;
    }

    /** @return array{url: string, headers: array<string, string>, partNumber: int} */
    public function signPart(
        FilesystemAdapter $disk,
        string $path,
        string $uploadId,
        int $partNumber,
        DateTimeInterface $expiration,
    ): array {
        try {
            $client = $this->client($disk);
            $command = $this->invoke($client, 'getCommand', [
                'UploadPart',
                [
                    ...$this->objectArguments($disk, $path),
                    'UploadId' => $uploadId,
                    'PartNumber' => $partNumber,
                ],
            ]);
            $request = $this->invoke($client, 'createPresignedRequest', [$command, $expiration]);
        } catch (Throwable $exception) {
            throw new RuntimeException('The S3 upload part could not be signed.', 0, $exception);
        }

        if (! is_object($request) || ! method_exists($request, 'getUri')) {
            throw new RuntimeException('The S3 client returned an invalid presigned request.');
        }

        $url = (string) $this->invoke($request, 'getUri');
        $headers = method_exists($request, 'getHeaders')
            ? $this->normalizeHeaders($this->invoke($request, 'getHeaders'))
            : [];

        return [
            'url' => $url,
            'headers' => $headers,
            'partNumber' => $partNumber,
        ];
    }

    /** @return list<array{partNumber: int, size: int, etag: string}> */
    public function status(FilesystemAdapter $disk, string $path, string $uploadId): array
    {
        $parts = [];
        $marker = null;

        try {
            do {
                $previousMarker = $marker;
                $arguments = [
                    ...$this->objectArguments($disk, $path),
                    'UploadId' => $uploadId,
                ];

                if ($marker !== null) {
                    $arguments['PartNumberMarker'] = $marker;
                }

                $result = $this->execute($disk, 'ListParts', $arguments);
                $listed = $this->resultValue($result, 'Parts');

                if (is_iterable($listed)) {
                    foreach ($listed as $part) {
                        $partNumber = $this->resultValue($part, 'PartNumber');
                        $size = $this->resultValue($part, 'Size');
                        $etag = $this->resultValue($part, 'ETag');

                        if (is_numeric($partNumber) && is_numeric($size) && is_string($etag)) {
                            $parts[] = [
                                'partNumber' => (int) $partNumber,
                                'size' => (int) $size,
                                'etag' => $etag,
                            ];
                        }
                    }
                }

                $truncated = $this->resultValue($result, 'IsTruncated') === true;
                $nextMarker = $this->resultValue($result, 'NextPartNumberMarker');
                $marker = is_numeric($nextMarker) ? (int) $nextMarker : null;

                if ($truncated && ($marker === null || $marker === $previousMarker)) {
                    throw new RuntimeException('The S3 client returned an invalid part-list cursor.');
                }
            } while ($truncated);
        } catch (Throwable $exception) {
            throw new RuntimeException('The S3 multipart upload status could not be retrieved.', 0, $exception);
        }

        usort($parts, fn (array $left, array $right): int => $left['partNumber'] <=> $right['partNumber']);

        return $parts;
    }

    /** @param list<array{partNumber: int, etag: string}> $parts */
    public function complete(
        FilesystemAdapter $disk,
        string $path,
        string $uploadId,
        array $parts,
    ): void {
        $completedParts = array_map(static fn (array $part): array => [
            'ETag' => $part['etag'],
            'PartNumber' => $part['partNumber'],
        ], $parts);

        try {
            $this->execute($disk, 'CompleteMultipartUpload', [
                ...$this->objectArguments($disk, $path),
                'UploadId' => $uploadId,
                'MultipartUpload' => ['Parts' => $completedParts],
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('The S3 multipart upload could not be completed.', 0, $exception);
        }
    }

    public function abort(FilesystemAdapter $disk, string $path, string $uploadId): void
    {
        try {
            $this->execute($disk, 'AbortMultipartUpload', [
                ...$this->objectArguments($disk, $path),
                'UploadId' => $uploadId,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('The S3 multipart upload could not be aborted.', 0, $exception);
        }
    }

    /** @return array{Bucket: string, Key: string} */
    private function objectArguments(FilesystemAdapter $disk, string $path): array
    {
        $config = $disk->getConfig();
        $bucket = $config['bucket'] ?? null;

        if (! is_string($bucket) || $bucket === '') {
            throw new RuntimeException('The S3 disk must define a bucket.');
        }

        $segments = array_filter([
            is_string($config['root'] ?? null) ? trim($config['root'], '/') : null,
            is_string($config['prefix'] ?? null) ? trim($config['prefix'], '/') : null,
            ltrim($path, '/'),
        ], static fn (?string $segment): bool => $segment !== null && $segment !== '');

        return ['Bucket' => $bucket, 'Key' => implode('/', $segments)];
    }

    /** @return array<string, mixed> */
    private function createOptions(FilesystemAdapter $disk): array
    {
        $config = $disk->getConfig();
        $configured = is_array($config['options'] ?? null) ? $config['options'] : [];
        $options = array_intersect_key($configured, array_flip($this->createOptionNames()));

        if (($config['visibility'] ?? null) === 'public' && ! isset($options['ACL'])) {
            $options['ACL'] = 'public-read';
        }

        return $options;
    }

    /** @return list<string> */
    private function createOptionNames(): array
    {
        return [
            'ACL',
            'BucketKeyEnabled',
            'CacheControl',
            'ChecksumAlgorithm',
            'ContentDisposition',
            'ContentEncoding',
            'ContentLanguage',
            'Expires',
            'GrantFullControl',
            'GrantRead',
            'GrantReadACP',
            'GrantWriteACP',
            'Metadata',
            'ObjectLockLegalHoldStatus',
            'ObjectLockMode',
            'ObjectLockRetainUntilDate',
            'RequestPayer',
            'SSECustomerAlgorithm',
            'SSECustomerKey',
            'SSECustomerKeyMD5',
            'SSEKMSKeyId',
            'ServerSideEncryption',
            'StorageClass',
            'Tagging',
            'WebsiteRedirectLocation',
        ];
    }

    private function client(FilesystemAdapter $disk): object
    {
        if (! method_exists($disk, 'getClient')) {
            throw new RuntimeException('The filesystem disk does not expose an S3 client.');
        }

        $client = $this->invoke($disk, 'getClient');

        if (! is_object($client)) {
            throw new RuntimeException('The filesystem disk returned an invalid S3 client.');
        }

        return $client;
    }

    /** @param array<string, mixed> $arguments */
    private function execute(FilesystemAdapter $disk, string $commandName, array $arguments): mixed
    {
        $client = $this->client($disk);
        $command = $this->invoke($client, 'getCommand', [$commandName, $arguments]);

        return $this->invoke($client, 'execute', [$command]);
    }

    /** @param list<mixed> $arguments */
    private function invoke(object $target, string $method, array $arguments = []): mixed
    {
        if (! method_exists($target, $method)) {
            throw new RuntimeException($target::class.' does not support '.$method.'().');
        }

        return (new ReflectionMethod($target, $method))->invokeArgs($target, $arguments);
    }

    private function resultValue(mixed $result, string $key): mixed
    {
        if (is_array($result)) {
            return $result[$key] ?? null;
        }

        if ($result instanceof ArrayAccess) {
            return $result[$key] ?? null;
        }

        if (is_object($result) && method_exists($result, 'get')) {
            return $this->invoke($result, 'get', [$key]);
        }

        return null;
    }

    /** @return array<string, string> */
    private function normalizeHeaders(mixed $headers): array
    {
        if (! is_array($headers)) {
            return [];
        }

        $normalized = [];

        foreach ($headers as $name => $value) {
            if (! is_string($name)) {
                continue;
            }

            if (is_array($value)) {
                $value = implode(', ', array_filter($value, is_string(...)));
            }

            if (is_string($value)) {
                $normalized[$name] = $value;
            }
        }

        return $normalized;
    }
}
