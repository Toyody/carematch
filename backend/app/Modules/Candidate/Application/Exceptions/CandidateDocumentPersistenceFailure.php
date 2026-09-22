<?php

namespace App\Modules\Candidate\Application\Exceptions;

use RuntimeException;

final class CandidateDocumentPersistenceFailure extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Candidate document metadata operation failed.');
    }
}
