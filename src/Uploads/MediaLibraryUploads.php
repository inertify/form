<?php

declare(strict_types=1);

namespace Inertify\Form\Uploads;

use Illuminate\Http\Request;
use RuntimeException;

/**
 * Optional adapter for Spatie Media Library. The package is intentionally not
 * a hard dependency; compatible models are detected through their public API.
 */
final class MediaLibraryUploads
{
    /**
     * @return list<object>
     */
    public static function syncCollection(
        Request $request,
        object $model,
        string $field,
        string $collection = 'default',
        ?string $disk = null,
    ): array {
        if (! method_exists($model, 'getMedia')) {
            throw new RuntimeException('Media Library uploads require a model that exposes getMedia().');
        }

        $mediaItems = $model->getMedia($collection);

        if (! is_iterable($mediaItems)) {
            throw new RuntimeException('The media model did not return an iterable collection.');
        }

        /** @var array<string, object> $current */
        $current = [];

        foreach ($mediaItems as $media) {
            if (is_object($media)) {
                $current[(string) self::mediaId($media)] = $media;
            }
        }
        $ordered = [];
        $manager = app(UploadManager::class);
        $uploads = app(UploadResolver::class)->ordered($request, $field);

        foreach ($uploads as $upload) {
            $existing = $upload->getExistingFile();
            $existingId = $existing?->getMetadata()['media_id'] ?? null;

            if ($existingId !== null && isset($current[(string) $existingId])) {
                $ordered[] = $current[(string) $existingId];

                continue;
            }

            $ordered[] = self::addUpload($model, $upload, $collection, $disk);

            // Media Library may move a local source or copy a remote source.
            // Directory deletion is deliberately idempotent for either outcome.
            if ($upload->isNew()) {
                $manager->delete($upload->getKey());
            }
        }

        $keptIds = array_map(self::mediaId(...), $ordered);

        foreach ($current as $media) {
            if (! in_array(self::mediaId($media), $keptIds, true) && method_exists($media, 'delete')) {
                $media->delete();
            }
        }

        foreach ($ordered as $index => $media) {
            self::setOrder($media, $index + 1);
        }

        return $ordered;
    }

    private static function addUpload(
        object $model,
        SubmittedUpload $upload,
        string $collection,
        ?string $disk,
    ): object {
        $remote = $upload->getRemoteFile();

        if ($remote !== null) {
            if (! method_exists($model, 'addMediaFromDisk')) {
                throw new RuntimeException('The media model cannot add files from a filesystem disk.');
            }

            $adder = $model->addMediaFromDisk($remote->getPath(), $remote->getDisk());
        } else {
            $file = $upload->getUploadedFile();

            if ($file === null || ! method_exists($model, 'addMedia')) {
                throw new RuntimeException('The media model cannot add the submitted upload.');
            }

            $adder = $model->addMedia($file);
        }

        if (is_object($adder) && method_exists($adder, 'usingFileName')) {
            $adder = $adder->usingFileName($upload->getName());
        }

        if (! is_object($adder) || ! method_exists($adder, 'toMediaCollection')) {
            throw new RuntimeException('The media model returned an incompatible file adder.');
        }

        $media = $disk === null || $disk === ''
            ? $adder->toMediaCollection($collection)
            : $adder->toMediaCollection($collection, $disk);

        if (! is_object($media)) {
            throw new RuntimeException('Media Library did not return the stored media model.');
        }

        return $media;
    }

    private static function mediaId(object $media): int|string
    {
        if (method_exists($media, 'getKey')) {
            $key = $media->getKey();

            if (is_int($key) || is_string($key)) {
                return $key;
            }
        }

        $id = $media->id ?? null;

        if (is_int($id) || is_string($id)) {
            return $id;
        }

        throw new RuntimeException('A media item does not expose a scalar identifier.');
    }

    private static function setOrder(object $media, int $order): void
    {
        if (method_exists($media, 'setOrder')) {
            $media->setOrder($order);

            return;
        }

        if (method_exists($media, 'setAttribute') && method_exists($media, 'save')) {
            $media->setAttribute('order_column', $order);
            $media->save();
        }
    }
}
