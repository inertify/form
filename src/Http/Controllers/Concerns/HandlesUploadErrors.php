<?php

declare(strict_types=1);

namespace Inertify\Form\Http\Controllers\Concerns;

use Closure;
use Illuminate\Validation\ValidationException;
use Inertify\Form\Uploads\Exceptions\InvalidUploadToken;
use RuntimeException;

trait HandlesUploadErrors
{
    private function uploadOperation(Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (InvalidUploadToken|RuntimeException $exception) {
            throw ValidationException::withMessages([
                'upload' => [$exception->getMessage()],
            ]);
        }
    }
}
