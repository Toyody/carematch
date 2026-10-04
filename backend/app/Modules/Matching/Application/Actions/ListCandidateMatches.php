<?php

namespace App\Modules\Matching\Application\Actions;

use App\Modules\Matching\Application\Contracts\CandidateMatchReadModel;
use App\Modules\Matching\Application\Data\CandidateMatchPage;
use App\Modules\Matching\Application\Data\MatchCriteria;
use App\Modules\Matching\Application\Exceptions\MatchingJobNotFound;
use App\Modules\Organisation\Application\Data\TenantContext;
use DateTimeImmutable;

final readonly class ListCandidateMatches
{
    public function __construct(private CandidateMatchReadModel $matches) {}

    public function handle(
        TenantContext $tenant,
        int $jobId,
        MatchCriteria $criteria,
        DateTimeImmutable $today,
        int $qualificationWarningDays,
    ): CandidateMatchPage {
        return $this->matches->forJob(
            $tenant->organisationId,
            $jobId,
            $criteria,
            $today,
            $qualificationWarningDays,
        ) ?? throw new MatchingJobNotFound;
    }
}
