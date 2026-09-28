<?php

namespace App\Modules\Recruitment\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Application\Actions\ListJobQualificationRequirements;
use App\Modules\Recruitment\Application\Exceptions\JobQualificationRequirementNotFound;
use App\Modules\Recruitment\Interfaces\Http\Requests\JobQualificationRequirementRequest;
use App\Modules\Recruitment\Interfaces\Http\Resources\JobQualificationRequirementResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ListJobQualificationRequirementsController extends Controller
{
    public function __invoke(JobQualificationRequirementRequest $request, ListJobQualificationRequirements $action): AnonymousResourceCollection
    {
        try {
            return JobQualificationRequirementResource::collection($action->handle($request->tenantContext(), $request->routeId('job')));
        } catch (JobQualificationRequirementNotFound) {
            throw new NotFoundHttpException('Not Found');
        }
    }
}
