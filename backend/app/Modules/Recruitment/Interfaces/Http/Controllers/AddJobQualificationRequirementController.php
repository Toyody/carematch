<?php

namespace App\Modules\Recruitment\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Application\Actions\AddJobQualificationRequirement;
use App\Modules\Recruitment\Application\Exceptions\DuplicateJobQualificationRequirement;
use App\Modules\Recruitment\Application\Exceptions\JobQualificationRequirementNotFound;
use App\Modules\Recruitment\Interfaces\Http\Requests\JobQualificationRequirementRequest;
use App\Modules\Recruitment\Interfaces\Http\Resources\JobQualificationRequirementResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AddJobQualificationRequirementController extends Controller
{
    public function __invoke(JobQualificationRequirementRequest $request, AddJobQualificationRequirement $action): JsonResponse
    {
        try {
            $record = $action->handle($request->tenantContext(), $request->routeId('job'), (int) $request->validated('qualification_definition_id'));
        } catch (JobQualificationRequirementNotFound) {
            throw new NotFoundHttpException('Not Found');
        } catch (DuplicateJobQualificationRequirement) {
            throw new ConflictHttpException('This qualification is already required.');
        }

        return (new JobQualificationRequirementResource($record))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
