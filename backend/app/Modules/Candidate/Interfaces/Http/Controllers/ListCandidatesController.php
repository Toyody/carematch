<?php

namespace App\Modules\Candidate\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Application\Actions\ListCandidates;
use App\Modules\Candidate\Application\Data\CandidatePage;
use App\Modules\Candidate\Interfaces\Http\Requests\ListCandidatesRequest;
use App\Modules\Candidate\Interfaces\Http\Resources\CandidateResource;
use Illuminate\Http\JsonResponse;

final class ListCandidatesController extends Controller
{
    public function __invoke(
        ListCandidatesRequest $request,
        ListCandidates $listCandidates,
    ): JsonResponse {
        $page = $listCandidates->handle(
            $request->tenantContext(),
            $request->criteria(),
        );

        return response()->json([
            'data' => CandidateResource::collection($page->items)->resolve($request),
            'links' => $this->links($request, $page),
            'meta' => $this->meta($request, $page),
        ]);
    }

    /**
     * @return array{first: string, last: string, prev: string|null, next: string|null}
     */
    private function links(ListCandidatesRequest $request, CandidatePage $page): array
    {
        return [
            'first' => $request->fullUrlWithQuery(['page' => 1]),
            'last' => $request->fullUrlWithQuery(['page' => $page->lastPage]),
            'prev' => $page->currentPage > 1
                ? $request->fullUrlWithQuery(['page' => $page->currentPage - 1])
                : null,
            'next' => $page->currentPage < $page->lastPage
                ? $request->fullUrlWithQuery(['page' => $page->currentPage + 1])
                : null,
        ];
    }

    /**
     * @return array{current_page: int, from: int|null, last_page: int, path: string, per_page: int, to: int|null, total: int}
     */
    private function meta(ListCandidatesRequest $request, CandidatePage $page): array
    {
        $count = count($page->items);
        $from = $count === 0 ? null : (($page->currentPage - 1) * $page->perPage) + 1;

        return [
            'current_page' => $page->currentPage,
            'from' => $from,
            'last_page' => $page->lastPage,
            'path' => $request->url(),
            'per_page' => $page->perPage,
            'to' => $from === null ? null : $from + $count - 1,
            'total' => $page->total,
        ];
    }
}
