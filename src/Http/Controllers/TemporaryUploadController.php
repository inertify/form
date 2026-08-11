<?php

declare(strict_types=1);

namespace Inertify\Form\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertify\Form\Http\Controllers\Concerns\HandlesUploadErrors;
use Inertify\Form\Uploads\UploadManager;
use Inertify\Form\Uploads\UploadRules;

final readonly class TemporaryUploadController
{
    use HandlesUploadErrors;

    public function __construct(private UploadManager $uploads) {}

    public function store(Request $request): JsonResponse
    {
        $maxSize = (int) config('inertia-forms.file_uploads.temporary_uploads.max_size', 10240);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.$maxSize],
            'rulesToken' => ['nullable', 'string'],
            'uploadRulesToken' => ['nullable', 'string'],
        ]);
        $rulesToken = $validated['rulesToken'] ?? $validated['uploadRulesToken'] ?? null;
        $rules = $this->uploadOperation(
            fn () => is_string($rulesToken) ? UploadRules::fromToken($rulesToken) : null,
        );
        $upload = $this->uploadOperation(
            fn () => $this->uploads->store($request->file('file'), $rules, $request),
        );

        return response()->json($this->uploads->response($upload), Response::HTTP_CREATED);
    }

    public function destroy(Request $request): Response
    {
        $validated = $request->validate(['key' => ['required', 'string']]);
        $this->uploadOperation(fn () => $this->uploads->delete($validated['key']));

        return response()->noContent();
    }
}
