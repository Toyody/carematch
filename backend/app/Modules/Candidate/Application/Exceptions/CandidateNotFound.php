<?php

namespace App\Modules\Candidate\Application\Exceptions;

use RuntimeException;

final class CandidateNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Candidate not found.');
    }
}
