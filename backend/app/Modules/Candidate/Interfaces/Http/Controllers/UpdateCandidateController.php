<?php

namespace App\Modules\Candidate\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Application\Actions\UpdateCandidate;
use App\Modules\Candidate\Application\Exceptions\CandidateNotFound;
use App\Modules\Candidate\Interfaces\Http\Requests\UpdateCandidateRequest;
use App\Modules\Candidate\Interfaces\Http\Resources\CandidateResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UpdateCandidateController extends Controller
{
    public function __invoke(
        UpdateCandidateRequest $request,
        UpdateCandidate $updateCandidate,
    ): CandidateResource {
        try {
            $candidate = $updateCandidate->handle(
                $request->tenantContext(),
                $request->candidateId(),
                $request->candidateChanges(),
            );
        } catch (CandidateNotFound) {
            throw new NotFoundHttpException('Not Found');
        }

        return new CandidateResource($candidate);
    }
}
