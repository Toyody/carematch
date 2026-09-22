<?php

namespace App\Modules\Candidate\Application\Exceptions;

use RuntimeException;

final class CandidateDocumentStorageFailure extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Candidate document storage operation failed.');
    }
}
