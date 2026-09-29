<?php

namespace App\Modules\Candidate\Application\Actions;

use App\Modules\Candidate\Application\Contracts\CandidateQualificationStore;
use App\Modules\Candidate\Application\Exceptions\CandidateQualificationNotFound;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class DeleteCandidateQualification
{
    public function __construct(private CandidateQualificationStore $store) {}

    public function handle(TenantContext $tenant, int $candidateId, int $credentialId): void
    {
        if (! $this->store->delete($tenant->organisationId, $tenant->userId, $candidateId, $credentialId)) {
            throw new CandidateQualificationNotFound;
        }
    }
}
