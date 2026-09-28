<?php

namespace App\Modules\Candidate\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Application\Actions\UpdateCandidateQualification;
use App\Modules\Candidate\Application\Exceptions\CandidateQualificationNotFound;
use App\Modules\Candidate\Interfaces\Http\Requests\CandidateQualificationRequest;
use App\Modules\Candidate\Interfaces\Http\Resources\CandidateQualificationResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UpdateCandidateQualificationController extends Controller
{
    public function __invoke(CandidateQualificationRequest $request, UpdateCandidateQualification $action): CandidateQualificationResource
    {
        try {
            return new CandidateQualificationResource($action->handle(
                $request->tenantContext(), $request->routeId('candidate'), $request->routeId('candidateQualification'), $request->qualificationData(),
            ));
        } catch (CandidateQualificationNotFound) {
            throw new NotFoundHttpException('Not Found');
        }
    }
}
