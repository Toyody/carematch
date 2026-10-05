<?php

namespace App\Modules\Ai\Application\Data;

final readonly class MatchExplanationWork
{
    public function __construct(
        public int $id,
        public int $organisationId,
        public int $jobId,
        public int $candidateId,
        public string $sourceFingerprint,
        public string $provider,
        public string $model,
    ) {}
}
