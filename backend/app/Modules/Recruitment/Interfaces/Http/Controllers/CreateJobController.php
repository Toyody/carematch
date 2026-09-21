<?php

namespace App\Modules\Recruitment\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Application\Actions\CreateJob;
use App\Modules\Recruitment\Interfaces\Http\Requests\CreateJobRequest;
use App\Modules\Recruitment\Interfaces\Http\Resources\JobResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CreateJobController extends Controller
{
    public function __invoke(CreateJobRequest $request, CreateJob $createJob): JsonResponse
    {
        return (new JobResource($createJob->handle($request->tenantContext(), $request->jobData())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
