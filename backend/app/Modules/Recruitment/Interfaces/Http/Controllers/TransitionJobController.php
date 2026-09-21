<?php

namespace App\Modules\Recruitment\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Application\Actions\TransitionJob;
use App\Modules\Recruitment\Application\Exceptions\JobNotFound;
use App\Modules\Recruitment\Application\Exceptions\JobTransitionNotAllowed;
use App\Modules\Recruitment\Interfaces\Http\Requests\TransitionJobRequest;
use App\Modules\Recruitment\Interfaces\Http\Resources\JobResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TransitionJobController extends Controller
{
    public function __invoke(TransitionJobRequest $request, TransitionJob $transitionJob): JobResource|JsonResponse
    {
        try {
            return new JobResource($transitionJob->handle(
                $request->tenantContext(),
                $request->jobId(),
                $request->targetStatus(),
            ));
        } catch (JobNotFound) {
            throw new NotFoundHttpException('Not Found');
        } catch (JobTransitionNotAllowed) {
            return response()->json(
                ['message' => 'The requested job status transition is not allowed.'],
                Response::HTTP_CONFLICT,
            );
        }
    }
}
