<?php

declare(strict_types=1);

namespace Inertify\Form\Uploads;

use Illuminate\Http\UploadedFile as LaravelUploadedFile;

final class UploadedFile extends LaravelUploadedFile
{
    public static function fromPath(
        string $path,
        string $originalName,
        ?string $mimeType = null,
    ): self {
        return new self($path, $originalName, $mimeType, UPLOAD_ERR_OK, true);
    }
}
