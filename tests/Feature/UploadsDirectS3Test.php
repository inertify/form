<?php

declare(strict_types=1);

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertify\Form\Uploads\UploadManager;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('s', 32)));
    config()->set('inertia-forms.file_uploads.direct_to_storage.disk', 's3-direct');
    config()->set('inertia-forms.file_uploads.direct_to_storage.multipart_threshold', 1);
    config()->set('inertia-forms.file_uploads.direct_to_storage.part_size', 5 * 1024 * 1024);

    Route::inertiaFormUploads(prefix: '/test-s3-uploads', middleware: [], name: 'test-s3-forms');
});

it('uses native S3 multipart commands when the Laravel disk exposes a client', function (): void {
    $size = (5 * 1024 * 1024) + 1;
    $client = new DirectFeatureFakeS3Client($size);
    $disk = new DirectFeatureFakeS3Disk($client, [
        'bucket' => 'direct-bucket',
        'root' => 'application',
    ]);
    Storage::set('s3-direct', $disk);

    $start = $this->postJson(route('test-s3-forms.file-upload.direct.start'), [
        'name' => 'large.bin',
        'size' => $size,
        'mimeType' => 'application/octet-stream',
    ])->assertCreated()->assertJsonPath('mode', 'multipart');
    $uploadId = $start->json('uploadId');

    $this->postJson(route('test-s3-forms.file-upload.direct.part'), [
        'uploadId' => $uploadId,
        'partNumber' => 1,
    ])->assertOk()
        ->assertJsonPath('url', 'https://s3.test/signed-part')
        ->assertJsonPath('partNumber', 1);

    $this->getJson(route('test-s3-forms.file-upload.direct.status', ['uploadId' => $uploadId]))
        ->assertOk()
        ->assertJsonPath('parts.0.size', 5 * 1024 * 1024)
        ->assertJsonPath('parts.1.size', 1);

    $this->postJson(route('test-s3-forms.file-upload.direct.complete'), [
        'uploadId' => $uploadId,
        'parts' => [
            ['partNumber' => 1, 'etag' => '"part-one"'],
            ['partNumber' => 2, 'etag' => '"part-two"'],
        ],
    ])->assertOk()->assertJsonPath('name', 'large.bin');

    expect(array_column($client->executed, 'name'))->toBe([
        'CreateMultipartUpload',
        'ListParts',
        'ListParts',
        'CompleteMultipartUpload',
    ])->and($client->signed['arguments'])->toMatchArray([
        'Bucket' => 'direct-bucket',
        'UploadId' => 'feature-provider-upload',
        'PartNumber' => 1,
    ])->and($client->completed)->toBeTrue();
});

it('returns the disk temporary upload URL and headers for a single direct upload', function (): void {
    config()->set('inertia-forms.file_uploads.direct_to_storage.multipart_threshold', 100);
    $client = new DirectFeatureFakeS3Client(7);
    Storage::set('s3-direct', new DirectFeatureFakeS3Disk($client, [
        'bucket' => 'direct-bucket',
    ]));

    $this->postJson(route('test-s3-forms.file-upload.direct.start'), [
        'name' => 'single.txt',
        'size' => 7,
        'mimeType' => 'text/plain',
    ])->assertCreated()
        ->assertJsonPath('mode', 'single')
        ->assertJsonPath('uploadUrl', 'https://s3.test/single-upload')
        ->assertJsonPath('headers.Content-Type', 'text/plain');

    expect($client->executed)->toBe([]);
});

it('finalizes a signed single upload away from its reusable staging URL', function (): void {
    config()->set('inertia-forms.file_uploads.direct_to_storage.multipart_threshold', 100);
    $client = new DirectFeatureFakeS3Client(8);
    $disk = new DirectFeatureFakeS3Disk($client, ['bucket' => 'direct-bucket']);
    Storage::set('s3-direct', $disk);

    $start = $this->postJson(route('test-s3-forms.file-upload.direct.start'), [
        'name' => 'signed.txt',
        'size' => 8,
        'mimeType' => 'text/plain',
    ])->assertCreated()
        ->assertJsonPath('mode', 'single')
        ->assertJsonPath('uploadUrl', 'https://s3.test/single-upload');
    $uploadId = $start->json('uploadId');
    $metadata = app(UploadManager::class)->directMetadata($uploadId);
    $disk->externalPut($metadata['path'], 'original');

    $key = $this->postJson(route('test-s3-forms.file-upload.direct.complete'), [
        'uploadId' => $uploadId,
    ])->assertOk()->json('key');
    $upload = Request::create('/submit', 'POST', ['file' => $key])->formUpload('file');
    $finalPath = (string) $upload?->getPath();

    expect($finalPath)->not->toBe($metadata['path'])
        ->and($disk->get($finalPath))->toBe('original');

    // A presigned PUT may remain valid until its original expiry, but it can
    // now only recreate the staging object and cannot replace finalized bytes.
    $disk->externalPut($metadata['path'], 'tampered');

    expect($disk->get($metadata['path']))->toBe('tampered')
        ->and($disk->get($finalPath))->toBe('original')
        ->and(Request::create('/submit', 'POST', ['file' => $key])->formUpload('file'))
        ->not->toBeNull();
});

it('aborts expired pending S3 multipart sessions before cleanup deletion', function (): void {
    Storage::fake('s3-cleanup-temporary');
    config()->set('inertia-forms.file_uploads.temporary_uploads.disk', 's3-cleanup-temporary');
    $size = (5 * 1024 * 1024) + 1;
    $client = new DirectFeatureFakeS3Client($size);
    $disk = new DirectFeatureFakeS3Disk($client, ['bucket' => 'direct-bucket']);
    Storage::set('s3-direct', $disk);
    $manager = app(UploadManager::class);
    $started = $manager->startDirect('abandoned.bin', $size, 'application/octet-stream');
    $metadata = $manager->directMetadata($started['uploadId']);
    $this->travel($manager->directUrlLifetime() + 1)->seconds();

    $report = $manager->cleanupReport();

    expect($report)->toMatchArray(['removed' => 1, 'failed' => 0, 'errors' => []])
        ->and(array_column($client->executed, 'name'))->toBe([
            'CreateMultipartUpload',
            'AbortMultipartUpload',
        ]);
    $disk->assertMissing($metadata['directory'].'/.metadata.json');
});

it('retains failed S3 cleanup for a successful retry', function (): void {
    Storage::fake('s3-cleanup-temporary');
    config()->set('inertia-forms.file_uploads.temporary_uploads.disk', 's3-cleanup-temporary');
    $size = (5 * 1024 * 1024) + 1;
    $client = new DirectFeatureFakeS3Client($size);
    $disk = new DirectFeatureFakeS3Disk($client, ['bucket' => 'direct-bucket']);
    Storage::set('s3-direct', $disk);
    $manager = app(UploadManager::class);
    $started = $manager->startDirect('retry.bin', $size, 'application/octet-stream');
    $metadata = $manager->directMetadata($started['uploadId']);
    $this->travel($manager->directUrlLifetime() + 1)->seconds();
    $client->abortFails = true;

    $failed = $manager->cleanupReport();

    expect($failed['removed'])->toBe(0)
        ->and($failed['failed'])->toBe(1);
    $disk->assertExists($metadata['directory'].'/.metadata.json');

    $client->abortFails = false;
    $retried = $manager->cleanupReport();

    expect($retried['removed'])->toBe(1)
        ->and($retried['failed'])->toBe(0);
    $disk->assertMissing($metadata['directory'].'/.metadata.json');
});

final class DirectFeatureFakeS3Disk extends FilesystemAdapter
{
    /** @var array<string, string> */
    private array $objects = [];

    /** @param array<string, mixed> $diskConfig */
    public function __construct(
        private readonly DirectFeatureFakeS3Client $s3Client,
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

    public function put($path, $contents, $options = [])
    {
        $this->objects[(string) $path] = (string) $contents;

        return true;
    }

    public function externalPut(string $path, string $contents): void
    {
        $this->objects[$path] = $contents;
    }

    public function delete($paths)
    {
        foreach ((array) $paths as $path) {
            unset($this->objects[(string) $path]);

            if ($this->providerObjectMatches((string) $path)) {
                $this->s3Client->objectPath = null;
            }
        }

        return true;
    }

    public function get($path)
    {
        return $this->objects[(string) $path] ?? null;
    }

    public function directories($directory = null, $recursive = false)
    {
        $directories = [];

        foreach (array_keys($this->objects) as $path) {
            $directories[] = explode('/', $path, 2)[0];
        }

        if ($this->s3Client->objectPath !== null) {
            $directories[] = explode('/', $this->adapterObjectPath(), 2)[0];
        }

        return array_values(array_unique($directories));
    }

    public function deleteDirectory($directory)
    {
        $prefix = rtrim((string) $directory, '/').'/';

        foreach (array_keys($this->objects) as $path) {
            if (str_starts_with($path, $prefix)) {
                unset($this->objects[$path]);
            }
        }

        if ($this->s3Client->objectPath !== null && str_starts_with($this->adapterObjectPath(), $prefix)) {
            $this->s3Client->objectPath = null;
        }

        return true;
    }

    public function exists($path)
    {
        return $this->providerObjectMatches((string) $path) || isset($this->objects[(string) $path]);
    }

    public function size($path)
    {
        return $this->providerObjectMatches((string) $path)
            ? $this->s3Client->objectSize
            : strlen($this->objects[(string) $path] ?? '');
    }

    public function move($from, $to)
    {
        $from = (string) $from;
        $to = (string) $to;

        if ($this->providerObjectMatches($from)) {
            $this->s3Client->objectPath = $this->providerPath($to);

            return true;
        }

        if (! isset($this->objects[$from])) {
            return false;
        }

        $this->objects[$to] = $this->objects[$from];
        unset($this->objects[$from]);

        return true;
    }

    public function readStream($path)
    {
        $contents = $this->providerObjectMatches((string) $path)
            ? str_repeat('x', $this->s3Client->objectSize)
            : ($this->objects[(string) $path] ?? null);

        if (! is_string($contents)) {
            return false;
        }

        $stream = fopen('php://temp', 'w+b');

        if (! is_resource($stream)) {
            return false;
        }

        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    public function temporaryUploadUrl($path, $expiration, array $options = [])
    {
        return [
            'url' => 'https://s3.test/single-upload',
            'headers' => ['Content-Type' => $options['ContentType'] ?? 'application/octet-stream'],
        ];
    }

    private function providerObjectMatches(string $path): bool
    {
        return $this->s3Client->objectPath === $this->providerPath($path);
    }

    private function providerPath(string $path): string
    {
        $root = trim((string) ($this->diskConfig['root'] ?? ''), '/');

        return ($root === '' ? '' : $root.'/').ltrim($path, '/');
    }

    private function adapterObjectPath(): string
    {
        $path = (string) $this->s3Client->objectPath;
        $root = trim((string) ($this->diskConfig['root'] ?? ''), '/');

        return $root !== '' && str_starts_with($path, $root.'/')
            ? substr($path, strlen($root) + 1)
            : $path;
    }
}

final class DirectFeatureFakeS3Client
{
    /** @var list<array{name: string, arguments: array<string, mixed>}> */
    public array $executed = [];

    /** @var array{name: string, arguments: array<string, mixed>} */
    public array $signed = [];

    public bool $completed = false;

    public ?string $objectPath = null;

    public bool $abortFails = false;

    public function __construct(public readonly int $objectSize) {}

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

        if ($command['name'] === 'AbortMultipartUpload' && $this->abortFails) {
            throw new RuntimeException('Provider abort failed.');
        }

        if ($command['name'] === 'CompleteMultipartUpload') {
            $this->completed = true;
            $this->objectPath = (string) $command['arguments']['Key'];
        }

        return match ($command['name']) {
            'CreateMultipartUpload' => ['UploadId' => 'feature-provider-upload'],
            'ListParts' => [
                'IsTruncated' => false,
                'Parts' => [
                    ['PartNumber' => 1, 'Size' => 5 * 1024 * 1024, 'ETag' => '"part-one"'],
                    ['PartNumber' => 2, 'Size' => 1, 'ETag' => '"part-two"'],
                ],
            ],
            default => [],
        };
    }

    /** @param array{name: string, arguments: array<string, mixed>} $command */
    public function createPresignedRequest(array $command, DateTimeInterface $expiration): DirectFeatureFakeSignedRequest
    {
        $this->signed = $command;

        return new DirectFeatureFakeSignedRequest;
    }
}

final class DirectFeatureFakeSignedRequest
{
    public function getUri(): string
    {
        return 'https://s3.test/signed-part';
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return [];
    }
}
