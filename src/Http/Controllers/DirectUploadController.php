<?php

declare(strict_types=1);

namespace Inertify\Form\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertify\Form\Http\Controllers\Concerns\HandlesUploadErrors;
use Inertify\Form\Uploads\UploadManager;
use Inertify\Form\Uploads\UploadRules;
use RuntimeException;
use Throwable;

final readonly class DirectUploadController
{
    use HandlesUploadErrors;

    public function __construct(private UploadManager $uploads) {}

    public function start(Request $request): JsonResponse
    {
        $maxSize = (int) config('inertia-forms.file_uploads.direct_to_storage.max_size', 5 * 1024 * 1024) * 1024;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.$maxSize],
            'mimeType' => ['nullable', 'string', 'max:255'],
            'disk' => ['nullable', 'string'],
            'rulesToken' => ['nullable', 'string'],
            'uploadRulesToken' => ['nullable', 'string'],
        ]);
        $rulesToken = $validated['rulesToken'] ?? $validated['uploadRulesToken'] ?? null;
        $rules = $this->uploadOperation(
            fn () => is_string($rulesToken) ? UploadRules::fromToken($rulesToken) : null,
        );
        $result = $this->uploadOperation(fn () => $this->uploads->startDirect(
            $validated['name'],
            $validated['size'],
            $validated['mimeType'] ?? null,
            $validated['disk'] ?? null,
            $rules,
        ));

        if ($result['mode'] === 'single') {
            $metadata = $this->uploads->directMetadata($result['uploadId']);
            $temporaryUrl = $this->temporaryUploadUrl($metadata);

            if ($temporaryUrl !== null) {
                $result['uploadUrl'] = $temporaryUrl['url'];
                $result['headers'] = $temporaryUrl['headers'];
            } else {
                $result['uploadUrl'] = $this->objectUrl($request, $result['uploadId']);
            }
        }

        return response()->json($result, Response::HTTP_CREATED);
    }

    public function object(Request $request): Response
    {
        $validated = $request->validate([
            'uploadId' => ['required', 'string'],
            'partNumber' => ['nullable', 'integer', 'min:1'],
        ]);
        $stream = $request->getContent(true);

        if (! is_resource($stream)) {
            throw new RuntimeException('The direct upload request body is not readable.');
        }

        $this->uploadOperation(fn () => $this->uploads->putDirectObject(
            $validated['uploadId'],
            $stream,
            isset($validated['partNumber']) ? (int) $validated['partNumber'] : null,
        ));

        return response()->noContent(headers: ['ETag' => '"'.hash('sha256', $request->getContent()).'"']);
    }

    public function part(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uploadId' => ['required', 'string'],
            'partNumber' => ['required', 'integer', 'min:1'],
        ]);

        $signed = $this->uploadOperation(fn () => $this->uploads->signDirectPart(
            $validated['uploadId'],
            (int) $validated['partNumber'],
        ));

        return response()->json($signed ?? [
            'url' => $this->objectUrl($request, $validated['uploadId'], (int) $validated['partNumber']),
            'headers' => [],
            'partNumber' => (int) $validated['partNumber'],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate(['uploadId' => ['required', 'string']]);

        return response()->json(
            $this->uploadOperation(fn () => $this->uploads->directStatus($validated['uploadId'])),
        );
    }

    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uploadId' => ['required', 'string'],
            'parts' => ['sometimes', 'array'],
            'parts.*.partNumber' => ['required', 'integer', 'min:1', 'max:10000'],
            'parts.*.etag' => ['required', 'string', 'max:255'],
        ]);
        /** @var list<array{partNumber: int, etag: string}> $parts */
        $parts = $validated['parts'] ?? [];
        $upload = $this->uploadOperation(
            fn () => $this->uploads->completeDirect($validated['uploadId'], $parts, $request),
        );

        return response()->json($this->uploads->response($upload));
    }

    public function abort(Request $request): Response
    {
        $validated = $request->validate(['uploadId' => ['required', 'string']]);
        $this->uploadOperation(fn () => $this->uploads->abortDirect($validated['uploadId']));

        return response()->noContent();
    }

    /** @param array<string, mixed> $metadata
     * @return array{url: string, headers: array<string, string>}|null
     */
    private function temporaryUploadUrl(array $metadata): ?array
    {
        try {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk((string) $metadata['disk']);
            $result = $disk->temporaryUploadUrl(
                (string) $metadata['path'],
                now()->addSeconds($this->uploads->directUrlLifetime()),
                array_filter(['ContentType' => $metadata['mime_type'] ?? null]),
            );

            if (! is_string($result['url'] ?? null)) {
                return null;
            }

            return [
                'url' => $result['url'],
                'headers' => is_array($result['headers'] ?? null) ? $result['headers'] : [],
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function objectUrl(Request $request, string $uploadId, ?int $partNumber = null): string
    {
        $routeName = (string) $request->route()?->getName();
        $objectRoute = preg_replace('/\.start$|\.part$/', '.object', $routeName);

        if (! is_string($objectRoute) || $objectRoute === '') {
            throw new RuntimeException('The direct upload object route could not be resolved.');
        }

        return route($objectRoute, array_filter([
            'uploadId' => $uploadId,
            'partNumber' => $partNumber,
        ], fn (mixed $value): bool => $value !== null), absolute: false);
    }
}
