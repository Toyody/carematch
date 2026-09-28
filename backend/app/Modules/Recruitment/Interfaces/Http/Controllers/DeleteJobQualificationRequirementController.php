<?php

namespace App\Modules\Recruitment\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Application\Actions\DeleteJobQualificationRequirement;
use App\Modules\Recruitment\Application\Exceptions\JobQualificationRequirementNotFound;
use App\Modules\Recruitment\Interfaces\Http\Requests\JobQualificationRequirementRequest;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DeleteJobQualificationRequirementController extends Controller
{
    public function __invoke(JobQualificationRequirementRequest $request, DeleteJobQualificationRequirement $action): Response
    {
        try {
            $action->handle($request->tenantContext(), $request->routeId('job'), $request->routeId('requirement'));
        } catch (JobQualificationRequirementNotFound) {
            throw new NotFoundHttpException('Not Found');
        }

        return response()->noContent();
    }
}
