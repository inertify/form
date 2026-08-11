<?php

declare(strict_types=1);

namespace Inertify\Form\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertify\Form\Uploads\TemporaryUpload;
use Inertify\Form\Uploads\UploadRules;

interface ValidatesFileUploads
{
    public function validate(
        TemporaryUpload $upload,
        UploadedFile $file,
        UploadRules $rules,
        Request $request,
    ): void;
}
