<?php

declare(strict_types=1);

namespace Inertify\Form\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertify\Form\Http\Controllers\Concerns\HandlesUploadErrors;
use Inertify\Form\Uploads\UploadManager;
use Inertify\Form\Uploads\UploadRules;

final readonly class ChunkedUploadController
{
    use HandlesUploadErrors;

    public function __construct(private UploadManager $uploads) {}

    public function start(Request $request): JsonResponse
    {
        $maxSize = (int) config('inertia-forms.file_uploads.temporary_uploads.chunked.max_size', 2 * 1024 * 1024) * 1024;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.$maxSize],
            'mimeType' => ['nullable', 'string', 'max:255'],
            'rulesToken' => ['nullable', 'string'],
            'uploadRulesToken' => ['nullable', 'string'],
        ]);
        $rulesToken = $validated['rulesToken'] ?? $validated['uploadRulesToken'] ?? null;
        $rules = $this->uploadOperation(
            fn () => is_string($rulesToken) ? UploadRules::fromToken($rulesToken) : null,
        );
        $result = $this->uploadOperation(fn () => $this->uploads->startChunk(
            $validated['name'],
            $validated['size'],
            $validated['mimeType'] ?? null,
            $rules,
        ));

        return response()->json($result, Response::HTTP_CREATED);
    }

    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate(['uploadId' => ['required', 'string']]);

        return response()->json(
            $this->uploadOperation(fn () => $this->uploads->chunkStatus($validated['uploadId'])),
        );
    }

    public function chunk(Request $request): JsonResponse
    {
        $maxChunkKiB = (int) ceil($this->uploads->chunkSize() / 1024);
        $validated = $request->validate([
            'uploadId' => ['required', 'string'],
            'offset' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', 'max:'.$maxChunkKiB],
        ]);

        return response()->json($this->uploadOperation(fn () => $this->uploads->appendChunk(
            $validated['uploadId'],
            $validated['offset'],
            $request->file('chunk'),
        )));
    }

    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate(['uploadId' => ['required', 'string']]);
        $upload = $this->uploadOperation(
            fn () => $this->uploads->completeChunk($validated['uploadId'], $request),
        );

        return response()->json($this->uploads->response($upload));
    }

    public function abort(Request $request): Response
    {
        $validated = $request->validate(['uploadId' => ['required', 'string']]);
        $this->uploadOperation(fn () => $this->uploads->abortChunk($validated['uploadId']));

        return response()->noContent();
    }
}
