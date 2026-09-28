<?php

namespace App\Modules\Candidate\Application\Actions;

use App\Modules\Candidate\Application\Contracts\CandidateQualificationStore;
use App\Modules\Candidate\Application\Data\CandidateQualificationRecord;
use App\Modules\Candidate\Application\Exceptions\CandidateQualificationNotFound;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class ListCandidateQualifications
{
    public function __construct(private CandidateQualificationStore $store) {}

    /** @return list<CandidateQualificationRecord> */
    public function handle(TenantContext $tenant, int $candidateId): array
    {
        return $this->store->all($tenant->organisationId, $candidateId)
            ?? throw new CandidateQualificationNotFound;
    }
}
