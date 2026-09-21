<?php

namespace App\Modules\Recruitment\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Application\Actions\ViewJob;
use App\Modules\Recruitment\Application\Exceptions\JobNotFound;
use App\Modules\Recruitment\Interfaces\Http\Requests\ShowJobRequest;
use App\Modules\Recruitment\Interfaces\Http\Resources\JobResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShowJobController extends Controller
{
    public function __invoke(ShowJobRequest $request, ViewJob $viewJob): JobResource
    {
        try {
            return new JobResource($viewJob->handle($request->tenantContext(), $request->jobId()));
        } catch (JobNotFound) {
            throw new NotFoundHttpException('Not Found');
        }
    }
}
