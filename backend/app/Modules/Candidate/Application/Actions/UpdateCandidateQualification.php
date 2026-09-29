<?php

namespace App\Modules\Candidate\Application\Actions;

use App\Modules\Candidate\Application\Contracts\CandidateQualificationStore;
use App\Modules\Candidate\Application\Data\CandidateQualificationData;
use App\Modules\Candidate\Application\Data\CandidateQualificationRecord;
use App\Modules\Candidate\Application\Exceptions\CandidateQualificationNotFound;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class UpdateCandidateQualification
{
    public function __construct(private CandidateQualificationStore $store) {}

    public function handle(TenantContext $tenant, int $candidateId, int $credentialId, CandidateQualificationData $data): CandidateQualificationRecord
    {
        return $this->store->update($tenant->organisationId, $tenant->userId, $candidateId, $credentialId, $data)
            ?? throw new CandidateQualificationNotFound;
    }
}
