<?php

namespace App\Modules\Recruitment\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Application\Actions\CreateApplication;
use App\Modules\Recruitment\Application\Exceptions\ApplicationTargetNotFound;
use App\Modules\Recruitment\Application\Exceptions\DuplicateApplication;
use App\Modules\Recruitment\Application\Exceptions\JobNotOpenForApplication;
use App\Modules\Recruitment\Interfaces\Http\Requests\CreateApplicationRequest;
use App\Modules\Recruitment\Interfaces\Http\Resources\ApplicationResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CreateApplicationController extends Controller
{
    public function __invoke(CreateApplicationRequest $request, CreateApplication $create): JsonResponse
    {
        try {
            $application = $create->handle($request->tenantContext(), $request->jobId(), $request->candidateId());
        } catch (ApplicationTargetNotFound) {
            throw new NotFoundHttpException('Not Found');
        } catch (JobNotOpenForApplication) {
            return response()->json(
                ['message' => 'Applications may only be created for an open job.'],
                Response::HTTP_CONFLICT,
            );
        } catch (DuplicateApplication) {
            return response()->json(
                ['message' => 'This candidate already has an application for this job.'],
                Response::HTTP_CONFLICT,
            );
        }

        return (new ApplicationResource($application))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
