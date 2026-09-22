<?php

namespace App\Modules\Candidate\Application\Actions;

use App\Modules\Candidate\Application\Contracts\CandidateDetails;
use App\Modules\Candidate\Application\Contracts\CandidateDocumentStore;
use App\Modules\Candidate\Application\Data\CandidateDocumentRecord;
use App\Modules\Candidate\Application\Exceptions\CandidateNotFound;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class ListCandidateDocuments
{
    public function __construct(
        private CandidateDetails $candidates,
        private CandidateDocumentStore $documents,
    ) {}

    /**
     * @return list<CandidateDocumentRecord>
     */
    public function handle(TenantContext $tenant, int $candidateId): array
    {
        $this->ensureCandidateExists($tenant->organisationId, $candidateId);

        return $this->documents->list($tenant->organisationId, $candidateId);
    }

    private function ensureCandidateExists(int $organisationId, int $candidateId): void
    {
        if ($this->candidates->find($organisationId, $candidateId) === null) {
            throw new CandidateNotFound;
        }
    }
}
