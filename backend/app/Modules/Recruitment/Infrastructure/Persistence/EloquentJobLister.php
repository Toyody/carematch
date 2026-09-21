<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Recruitment\Application\Contracts\JobLister;
use App\Modules\Recruitment\Application\Data\JobListCriteria;
use App\Modules\Recruitment\Application\Data\JobPage;

final class EloquentJobLister implements JobLister
{
    public function list(int $organisationId, JobListCriteria $criteria): JobPage
    {
        $query = Job::query()->where('organisation_id', $organisationId);

        if ($criteria->search !== null) {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $criteria->search).'%';
            $query->whereRaw("title ILIKE ? ESCAPE E'\\\\'", [$pattern]);
        }
        if ($criteria->status !== null) {
            $query->where('status', $criteria->status->value);
        }
        if ($criteria->occupation !== null) {
            $query->where('occupation', $criteria->occupation);
        }
        if ($criteria->employmentType !== null) {
            $query->where('employment_type', $criteria->employmentType);
        }

        $direction = $criteria->direction === 'asc' ? 'asc' : 'desc';
        if ($criteria->sort === 'opened_at') {
            $query->orderByRaw('opened_at '.$direction.' NULLS LAST')->orderBy('id', $direction);
        } else {
            $query->orderBy('created_at', $direction)->orderBy('id', $direction);
        }

        $paginator = $query->paginate(perPage: $criteria->perPage, page: $criteria->page);
        $items = array_values($paginator->getCollection()
            ->map(static fn (Job $job) => EloquentJobMapper::toRecord($job))->all());

        return new JobPage($items, $paginator->currentPage(), $paginator->lastPage(), $paginator->perPage(), $paginator->total());
    }
}
