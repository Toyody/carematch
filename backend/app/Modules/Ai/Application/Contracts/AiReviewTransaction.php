<?php

namespace App\Modules\Ai\Application\Contracts;

use App\Modules\Candidate\Application\Data\CandidateRecord;
use Closure;

interface AiReviewTransaction
{
    /** @param Closure(): CandidateRecord $operation */
    public function run(Closure $operation): CandidateRecord;
}
