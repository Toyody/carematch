<?php

namespace App\Modules\Matching\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matching\Application\Actions\ListCandidateMatches;
use App\Modules\Matching\Application\Data\CandidateMatchPage;
use App\Modules\Matching\Application\Exceptions\JobCoordinatesRequired;
use App\Modules\Matching\Application\Exceptions\MatchingJobNotFound;
use App\Modules\Matching\Interfaces\Http\Requests\ListCandidateMatchesRequest;
use App\Modules\Matching\Interfaces\Http\Resources\CandidateMatchResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ListCandidateMatchesController extends Controller
{
    public function __invoke(ListCandidateMatchesRequest $request, ListCandidateMatches $action): JsonResponse
    {
        try {
            $page = $action->handle(
                $request->tenantContext(),
                $request->jobId(),
                $request->criteria(),
                CarbonImmutable::today('UTC'),
                max(0, (int) config('carematch.compliance.expiry_warning_days', 30)),
            );
        } catch (MatchingJobNotFound) {
            throw new NotFoundHttpException('Not Found');
        } catch (JobCoordinatesRequired) {
            throw ValidationException::withMessages([
                'max_distance_km' => ['Job coordinates are required when filtering by distance.'],
            ]);
        }

        return response()->json([
            'data' => CandidateMatchResource::collection($page->items)->resolve($request),
            'links' => $this->links($request, $page),
            'meta' => $this->meta($request, $page),
        ]);
    }

    /** @return array{first: string, last: string, prev: string|null, next: string|null} */
    private function links(ListCandidateMatchesRequest $request, CandidateMatchPage $page): array
    {
        return [
            'first' => $request->fullUrlWithQuery(['page' => 1]),
            'last' => $request->fullUrlWithQuery(['page' => $page->lastPage]),
            'prev' => $page->currentPage > 1 ? $request->fullUrlWithQuery(['page' => $page->currentPage - 1]) : null,
            'next' => $page->currentPage < $page->lastPage ? $request->fullUrlWithQuery(['page' => $page->currentPage + 1]) : null,
        ];
    }

    /** @return array{current_page: int, from: int|null, last_page: int, path: string, per_page: int, to: int|null, total: int} */
    private function meta(ListCandidateMatchesRequest $request, CandidateMatchPage $page): array
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
