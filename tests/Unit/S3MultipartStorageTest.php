<?php

declare(strict_types=1);

use Illuminate\Filesystem\FilesystemAdapter;
use Inertify\Form\Uploads\S3MultipartStorage;

it('drives an available S3 client without requiring AWS SDK types', function (): void {
    $client = new MultipartFakeS3Client;
    $disk = new MultipartFakeS3Disk($client, [
        'bucket' => 'uploads-bucket',
        'root' => 'tenant',
        'prefix' => 'private',
        'options' => ['ServerSideEncryption' => 'AES256'],
    ]);
    $storage = new S3MultipartStorage;

    expect($storage->supports($disk))->toBeTrue();

    $providerUploadId = $storage->start($disk, 'pending/archive.txt', 'text/plain');
    $signed = $storage->signPart(
        $disk,
        'pending/archive.txt',
        $providerUploadId,
        2,
        new DateTimeImmutable('+15 minutes'),
    );
    $parts = $storage->status($disk, 'pending/archive.txt', $providerUploadId);
    $storage->complete($disk, 'pending/archive.txt', $providerUploadId, [
        ['partNumber' => 1, 'etag' => '"etag-one"'],
        ['partNumber' => 2, 'etag' => '"etag-two"'],
    ]);
    $storage->abort($disk, 'pending/archive.txt', $providerUploadId);

    expect($providerUploadId)->toBe('provider-upload-id')
        ->and($signed)->toBe([
            'url' => 'https://storage.test/upload-part',
            'headers' => ['x-upload-header' => 'one, two'],
            'partNumber' => 2,
        ])
        ->and($parts)->toBe([
            ['partNumber' => 1, 'size' => 5, 'etag' => '"etag-one"'],
            ['partNumber' => 2, 'size' => 3, 'etag' => '"etag-two"'],
        ])
        ->and(array_column($client->executed, 'name'))->toBe([
            'CreateMultipartUpload',
            'ListParts',
            'CompleteMultipartUpload',
            'AbortMultipartUpload',
        ])
        ->and($client->executed[0]['arguments'])->toMatchArray([
            'Bucket' => 'uploads-bucket',
            'Key' => 'tenant/private/pending/archive.txt',
            'ContentType' => 'text/plain',
            'ServerSideEncryption' => 'AES256',
        ])
        ->and($client->signed['arguments'])->toMatchArray([
            'Bucket' => 'uploads-bucket',
            'Key' => 'tenant/private/pending/archive.txt',
            'UploadId' => 'provider-upload-id',
            'PartNumber' => 2,
        ]);
});

final class MultipartFakeS3Disk extends FilesystemAdapter
{
    /** @param array<string, mixed> $diskConfig */
    public function __construct(
        private readonly object $s3Client,
        private readonly array $diskConfig,
    ) {}

    public function getClient(): object
    {
        return $this->s3Client;
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        return $this->diskConfig;
    }
}

final class MultipartFakeS3Client
{
    /** @var list<array{name: string, arguments: array<string, mixed>}> */
    public array $executed = [];

    /** @var array{name: string, arguments: array<string, mixed>} */
    public array $signed = [];

    /** @param array<string, mixed> $arguments
     * @return array{name: string, arguments: array<string, mixed>}
     */
    public function getCommand(string $name, array $arguments): array
    {
        return compact('name', 'arguments');
    }

    /** @param array{name: string, arguments: array<string, mixed>} $command
     * @return array<string, mixed>
     */
    public function execute(array $command): array
    {
        $this->executed[] = $command;

        return match ($command['name']) {
            'CreateMultipartUpload' => ['UploadId' => 'provider-upload-id'],
            'ListParts' => [
                'IsTruncated' => false,
                'Parts' => [
                    ['PartNumber' => 2, 'Size' => 3, 'ETag' => '"etag-two"'],
                    ['PartNumber' => 1, 'Size' => 5, 'ETag' => '"etag-one"'],
                ],
            ],
            default => [],
        };
    }

    /** @param array{name: string, arguments: array<string, mixed>} $command */
    public function createPresignedRequest(array $command, DateTimeInterface $expiration): MultipartFakeSignedRequest
    {
        $this->signed = $command;

        return new MultipartFakeSignedRequest;
    }
}

final class MultipartFakeSignedRequest
{
    public function getUri(): string
    {
        return 'https://storage.test/upload-part';
    }

    /** @return array<string, list<string>> */
    public function getHeaders(): array
    {
        return ['x-upload-header' => ['one', 'two']];
    }
}
