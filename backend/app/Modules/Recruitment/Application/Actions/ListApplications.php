<?php

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Candidate\Application\Contracts\CandidateReferenceLookup;
use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Recruitment\Application\Contracts\ApplicationLister;
use App\Modules\Recruitment\Application\Data\ApplicationListCriteria;
use App\Modules\Recruitment\Application\Data\ApplicationRecord;
use App\Modules\Recruitment\Application\Data\ApplicationSummary;
use App\Modules\Recruitment\Application\Data\ApplicationSummaryPage;
use LogicException;

final readonly class ListApplications
{
    public function __construct(
        private ApplicationLister $applications,
        private CandidateReferenceLookup $candidates,
    ) {}

    public function handle(TenantContext $tenant, ApplicationListCriteria $criteria): ApplicationSummaryPage
    {
        $page = $this->applications->list($tenant->organisationId, $criteria);
        $candidates = $this->candidates->findMany($tenant->organisationId, array_values(array_unique(array_map(
            static fn (ApplicationRecord $application): int => $application->candidateId,
            $page->items,
        ))));

        $items = array_map(static function (ApplicationRecord $application) use ($candidates): ApplicationSummary {
            $candidate = $candidates[$application->candidateId] ?? null;
            if ($candidate === null) {
                throw new LogicException('An application references a missing candidate.');
            }

            return new ApplicationSummary(
                id: $application->id,
                jobId: $application->jobId,
                jobTitle: $application->jobTitle,
                candidateId: $candidate->id,
                candidateFirstName: $candidate->firstName,
                candidateLastName: $candidate->lastName,
                status: $application->status,
                appliedAt: $application->appliedAt,
                createdByUserId: $application->createdByUserId,
                createdAt: $application->createdAt,
                updatedAt: $application->updatedAt,
            );
        }, $page->items);

        return new ApplicationSummaryPage(
            $items,
            $page->currentPage,
            $page->lastPage,
            $page->perPage,
            $page->total,
        );
    }
}
