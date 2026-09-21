<?php

namespace App\Modules\Candidate\Application\Actions;

use App\Modules\Candidate\Application\Contracts\CandidateLister;
use App\Modules\Candidate\Application\Data\CandidateListCriteria;
use App\Modules\Candidate\Application\Data\CandidatePage;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class ListCandidates
{
    public function __construct(
        private CandidateLister $candidates,
    ) {}

    public function handle(TenantContext $tenant, CandidateListCriteria $criteria): CandidatePage
    {
        return $this->candidates->list($tenant->organisationId, $criteria);
    }
}
