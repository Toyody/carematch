<?php

namespace App\Modules\Ai\Application\Exceptions;

use RuntimeException;

final class PermanentAiFailure extends RuntimeException
{
    public function __construct(public readonly string $failureCode)
    {
        parent::__construct('The AI operation cannot be completed.');
    }
}
