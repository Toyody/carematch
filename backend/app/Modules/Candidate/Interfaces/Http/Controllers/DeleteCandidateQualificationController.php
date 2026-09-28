<?php

namespace App\Modules\Candidate\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Application\Actions\DeleteCandidateQualification;
use App\Modules\Candidate\Application\Exceptions\CandidateQualificationNotFound;
use App\Modules\Candidate\Interfaces\Http\Requests\CandidateQualificationRequest;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DeleteCandidateQualificationController extends Controller
{
    public function __invoke(CandidateQualificationRequest $request, DeleteCandidateQualification $action): Response
    {
        try {
            $action->handle($request->tenantContext(), $request->routeId('candidate'), $request->routeId('candidateQualification'));
        } catch (CandidateQualificationNotFound) {
            throw new NotFoundHttpException('Not Found');
        }

        return response()->noContent();
    }
}
