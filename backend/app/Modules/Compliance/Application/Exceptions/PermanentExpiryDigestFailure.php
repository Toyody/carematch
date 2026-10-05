<?php

namespace App\Modules\Compliance\Application\Exceptions;

use RuntimeException;

final class PermanentExpiryDigestFailure extends RuntimeException
{
    public function __construct(public readonly string $failureCode)
    {
        parent::__construct('The expiry digest cannot be delivered.');
    }
}
