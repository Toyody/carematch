<?php

namespace App\Modules\Candidate\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Application\Actions\ViewCandidate;
use App\Modules\Candidate\Application\Exceptions\CandidateNotFound;
use App\Modules\Candidate\Interfaces\Http\Requests\ShowCandidateRequest;
use App\Modules\Candidate\Interfaces\Http\Resources\CandidateResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShowCandidateController extends Controller
{
    public function __invoke(
        ShowCandidateRequest $request,
        ViewCandidate $viewCandidate,
    ): CandidateResource {
        try {
            $candidate = $viewCandidate->handle(
                $request->tenantContext(),
                $request->candidateId(),
            );
        } catch (CandidateNotFound) {
            throw new NotFoundHttpException('Not Found');
        }

        return new CandidateResource($candidate);
    }
}
