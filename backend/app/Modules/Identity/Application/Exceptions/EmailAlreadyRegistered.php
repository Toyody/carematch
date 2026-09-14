<?php

namespace App\Modules\Identity\Application\Exceptions;

use RuntimeException;
use Throwable;

final class EmailAlreadyRegistered extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('The email has already been registered.', previous: $previous);
    }
}
