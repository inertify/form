<?php

declare(strict_types=1);

namespace Inertify\Form\Uploads;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertify\Form\Uploads\Exceptions\InvalidUploadToken;

final readonly class UploadResolver
{
    public function __construct(private UploadManager $manager) {}

    public function one(Request $request, string $key): ?SubmittedUpload
    {
        /** @var SubmittedUpload|null */
        return $this->ordered($request, $key)->first();
    }

    /** @return Collection<int, SubmittedUpload> */
    public function ordered(Request $request, string $key): Collection
    {
        $cache = $request->attributes->get('__inertify_form_uploads', []);

        if (is_array($cache) && isset($cache[$key]) && $cache[$key] instanceof Collection) {
            /** @var Collection<int, SubmittedUpload> */
            return $cache[$key];
        }

        $value = $request->input($key);
        $tokens = is_array($value) ? $value : [$value];
        $uploads = collect();

        foreach ($tokens as $token) {
            if ($token === null || $token === '') {
                continue;
            }

            if (! is_string($token)) {
                throw ValidationException::withMessages([
                    $key => ['The upload token must be a string.'],
                ]);
            }

            try {
                $uploads->push($this->manager->resolve($token));
            } catch (InvalidUploadToken $exception) {
                throw ValidationException::withMessages([
                    $key => [$exception->getMessage()],
                ]);
            }
        }

        /** @var Collection<int, SubmittedUpload> $uploads */
        $cache = is_array($cache) ? $cache : [];
        $cache[$key] = $uploads;
        $request->attributes->set('__inertify_form_uploads', $cache);

        return $uploads;
    }

    public function hydrate(Request $request): void
    {
        foreach (Arr::dot($request->all()) as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            try {
                $this->manager->resolve($value);
            } catch (InvalidUploadToken) {
                continue;
            }

            $this->ordered($request, $key);
        }
    }
}
