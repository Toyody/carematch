<?php

namespace App\Modules\Ai\Infrastructure\Persistence;

use App\Modules\Ai\Application\Contracts\AiReviewTransaction;
use App\Modules\Candidate\Application\Data\CandidateRecord;
use Closure;
use Illuminate\Support\Facades\DB;

final class EloquentAiReviewTransaction implements AiReviewTransaction
{
    public function run(Closure $operation): CandidateRecord
    {
        /** @var CandidateRecord */
        return DB::transaction($operation);
    }
}
