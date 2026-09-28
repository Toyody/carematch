<?php

namespace App\Modules\Candidate\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Application\Actions\CreateCandidateQualification;
use App\Modules\Candidate\Application\Exceptions\CandidateQualificationNotFound;
use App\Modules\Candidate\Interfaces\Http\Requests\CandidateQualificationRequest;
use App\Modules\Candidate\Interfaces\Http\Resources\CandidateQualificationResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CreateCandidateQualificationController extends Controller
{
    public function __invoke(CandidateQualificationRequest $request, CreateCandidateQualification $action): JsonResponse
    {
        try {
            return (new CandidateQualificationResource($action->handle($request->tenantContext(), $request->routeId('candidate'), $request->qualificationData())))
                ->response()->setStatusCode(Response::HTTP_CREATED);
        } catch (CandidateQualificationNotFound) {
            throw new NotFoundHttpException('Not Found');
        }
    }
}
