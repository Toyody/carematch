<?php

namespace App\Modules\Candidate\Infrastructure\Persistence;

use App\Modules\Candidate\Application\Contracts\CandidateLister;
use App\Modules\Candidate\Application\Data\CandidateListCriteria;
use App\Modules\Candidate\Application\Data\CandidatePage;
use Illuminate\Database\Eloquent\Builder;

final class EloquentCandidateLister implements CandidateLister
{
    public function list(int $organisationId, CandidateListCriteria $criteria): CandidatePage
    {
        $query = Candidate::query()->where('organisation_id', $organisationId);

        if ($criteria->search !== null) {
            $this->applySearch($query, $criteria->search);
        }

        if ($criteria->occupation !== null) {
            $query->where('occupation', $criteria->occupation);
        }

        $this->applySort($query, $criteria);

        $paginator = $query->paginate(
            perPage: $criteria->perPage,
            page: $criteria->page,
        );

        $items = array_values($paginator->getCollection()
            ->map(static fn (Candidate $candidate) => EloquentCandidateMapper::toRecord($candidate))
            ->values()
            ->all());

        return new CandidatePage(
            items: $items,
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
        );
    }

    /**
     * @param  Builder<Candidate>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $pattern = '%'.str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $search,
        ).'%';

        $query->where(static function (Builder $searchQuery) use ($pattern): void {
            $searchQuery
                ->whereRaw("first_name ILIKE ? ESCAPE E'\\\\'", [$pattern])
                ->orWhereRaw("last_name ILIKE ? ESCAPE E'\\\\'", [$pattern])
                ->orWhereRaw("concat_ws(' ', first_name, last_name) ILIKE ? ESCAPE E'\\\\'", [$pattern])
                ->orWhereRaw("email ILIKE ? ESCAPE E'\\\\'", [$pattern]);
        });
    }

    /**
     * @param  Builder<Candidate>  $query
     */
    private function applySort(Builder $query, CandidateListCriteria $criteria): void
    {
        $direction = $criteria->direction === 'asc' ? 'asc' : 'desc';

        if ($criteria->sort === 'name') {
            $query
                ->orderBy('last_name', $direction)
                ->orderBy('first_name', $direction)
                ->orderBy('id', $direction);

            return;
        }

        $query
            ->orderBy('created_at', $direction)
            ->orderBy('id', $direction);
    }
}
