<?php

namespace App\Modules\Compliance\Application\Actions;

use App\Modules\Compliance\Application\Contracts\QualificationCoverageReadModel;
use App\Modules\Compliance\Application\Exceptions\QualificationCoverageTargetNotFound;
use App\Modules\Compliance\Domain\QualificationCoverage;
use App\Modules\Compliance\Domain\QualificationCoverageEvaluator;
use App\Modules\Organisation\Application\Data\TenantContext;
use DateTimeImmutable;

final readonly class EvaluateQualificationCoverage
{
    public function __construct(
        private QualificationCoverageReadModel $readModel,
        private QualificationCoverageEvaluator $evaluator,
    ) {}

    public function handle(TenantContext $tenant, int $jobId, int $candidateId, DateTimeImmutable $today, int $warningDays): QualificationCoverage
    {
        $data = $this->readModel->forCandidateAndJob($tenant->organisationId, $jobId, $candidateId)
            ?? throw new QualificationCoverageTargetNotFound;

        return $this->evaluator->evaluate($data['requirements'], $data['credentials'], $today, $warningDays);
    }
}
