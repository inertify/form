<?php

declare(strict_types=1);

namespace Inertify\Form\Uploads\Exceptions;

use RuntimeException;

final class InvalidUploadToken extends RuntimeException
{
    public static function malformed(): self
    {
        return new self('The upload token is invalid.');
    }

    public static function expired(): self
    {
        return new self('The upload token has expired.');
    }

    public static function unexpectedPurpose(): self
    {
        return new self('The upload token has an unexpected purpose.');
    }
}
