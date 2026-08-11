<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertify\Form\Uploads\ExistingFile;
use Inertify\Form\Uploads\MediaLibraryUploads;

it('synchronizes existing media selection and ordering without requiring Media Library', function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('m', 32)));
    Storage::fake('media');
    Storage::disk('media')->put('one.jpg', 'one');
    Storage::disk('media')->put('two.jpg', 'two');

    $one = ExistingFile::fromDisk('media', 'one.jpg', withPreview: false, metadata: ['media_id' => 1]);
    $two = ExistingFile::fromDisk('media', 'two.jpg', withPreview: false, metadata: ['media_id' => 2]);
    $media = [new FakeMedia(1), new FakeMedia(2), new FakeMedia(3)];
    $model = new FakeMediaModel($media);
    $request = Request::create('/submit', 'POST', [
        'photos' => [$two->getKey(), $one->getKey()],
    ]);

    $result = MediaLibraryUploads::syncCollection(
        request: $request,
        model: $model,
        field: 'photos',
        collection: 'photos',
    );

    expect(array_map(fn (FakeMedia $item): int => $item->id, $result))->toBe([2, 1])
        ->and($media[0]->order)->toBe(2)
        ->and($media[1]->order)->toBe(1)
        ->and($media[2]->deleted)->toBeTrue();
});

it('uses the configured media disk name instead of its filesystem driver', function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('m', 32)));
    Storage::fake('media-library');
    Storage::disk('media-library')->put('media/photo.jpg', 'photo');

    $file = ExistingFile::fromMediaLibrary(new FakeMediaWithConfiguredDisk);

    expect($file)->toBeInstanceOf(ExistingFile::class)
        ->and($file->getDisk())->toBe('media-library')
        ->and($file->getPath())->toBe('media/photo.jpg');
});

final class FakeMedia
{
    public bool $deleted = false;

    public ?int $order = null;

    public function __construct(public int $id) {}

    public function getKey(): int
    {
        return $this->id;
    }

    public function delete(): void
    {
        $this->deleted = true;
    }

    public function setOrder(int $order): void
    {
        $this->order = $order;
    }
}

final class FakeMediaModel
{
    /** @param list<FakeMedia> $media */
    public function __construct(private array $media) {}

    /** @return list<FakeMedia> */
    public function getMedia(string $collection): array
    {
        return $this->media;
    }
}

final class FakeMediaWithConfiguredDisk
{
    public int $id = 10;

    public string $disk = 'media-library';

    public string $collection_name = 'photos';

    public function getDiskDriverName(): string
    {
        return 'local';
    }

    public function getPathRelativeToRoot(): string
    {
        return 'media/photo.jpg';
    }
}
