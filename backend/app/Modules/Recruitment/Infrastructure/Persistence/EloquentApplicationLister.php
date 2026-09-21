<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Recruitment\Application\Contracts\ApplicationLister;
use App\Modules\Recruitment\Application\Data\ApplicationListCriteria;
use App\Modules\Recruitment\Application\Data\ApplicationPage;

final class EloquentApplicationLister implements ApplicationLister
{
    public function list(int $organisationId, ApplicationListCriteria $criteria): ApplicationPage
    {
        $query = RecruitmentApplication::query()
            ->join('jobs', function ($join): void {
                $join->on('jobs.organisation_id', '=', 'applications.organisation_id')
                    ->on('jobs.id', '=', 'applications.job_id');
            })
            ->where('applications.organisation_id', $organisationId)
            ->select(['applications.*', 'jobs.title as job_title']);

        if ($criteria->jobId !== null) {
            $query->where('applications.job_id', $criteria->jobId);
        }
        if ($criteria->candidateId !== null) {
            $query->where('applications.candidate_id', $criteria->candidateId);
        }
        if ($criteria->status !== null) {
            $query->where('applications.status', $criteria->status->value);
        }

        $direction = $criteria->direction === 'asc' ? 'asc' : 'desc';
        $sort = $criteria->sort === 'updated_at' ? 'applications.updated_at' : 'applications.applied_at';
        $query->orderBy($sort, $direction)->orderBy('applications.id', $direction);

        $paginator = $query->paginate(perPage: $criteria->perPage, page: $criteria->page);
        $items = array_values($paginator->getCollection()->map(
            static fn (RecruitmentApplication $application) => EloquentApplicationMapper::toRecord(
                $application,
                (string) $application->getAttribute('job_title'),
            ),
        )->all());

        return new ApplicationPage(
            $items,
            $paginator->currentPage(),
            $paginator->lastPage(),
            $paginator->perPage(),
            $paginator->total(),
        );
    }
}
