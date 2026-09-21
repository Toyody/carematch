<?php

namespace App\Modules\Candidate\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Application\Actions\CreateCandidate;
use App\Modules\Candidate\Interfaces\Http\Requests\CreateCandidateRequest;
use App\Modules\Candidate\Interfaces\Http\Resources\CandidateResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CreateCandidateController extends Controller
{
    public function __invoke(
        CreateCandidateRequest $request,
        CreateCandidate $createCandidate,
    ): JsonResponse {
        $candidate = $createCandidate->handle(
            $request->tenantContext(),
            $request->candidateData(),
        );

        return (new CandidateResource($candidate))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
