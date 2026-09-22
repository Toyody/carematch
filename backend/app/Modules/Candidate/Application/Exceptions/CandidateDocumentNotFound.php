<?php

namespace App\Modules\Candidate\Application\Exceptions;

use RuntimeException;

final class CandidateDocumentNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Candidate document not found.');
    }
}
