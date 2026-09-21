<?php

namespace App\Modules\Candidate\Application\Actions;

use App\Modules\Candidate\Application\Contracts\CandidateDetails;
use App\Modules\Candidate\Application\Data\CandidateRecord;
use App\Modules\Candidate\Application\Exceptions\CandidateNotFound;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class ViewCandidate
{
    public function __construct(
        private CandidateDetails $candidates,
    ) {}

    public function handle(TenantContext $tenant, int $candidateId): CandidateRecord
    {
        return $this->candidates->find($tenant->organisationId, $candidateId)
            ?? throw new CandidateNotFound;
    }
}
