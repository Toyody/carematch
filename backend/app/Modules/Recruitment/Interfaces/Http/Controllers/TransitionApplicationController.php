<?php

namespace App\Modules\Recruitment\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Application\Actions\TransitionApplication;
use App\Modules\Recruitment\Application\Exceptions\ApplicationNotFound;
use App\Modules\Recruitment\Application\Exceptions\ApplicationTransitionForbidden;
use App\Modules\Recruitment\Application\Exceptions\ApplicationTransitionNotAllowed;
use App\Modules\Recruitment\Interfaces\Http\Requests\TransitionApplicationRequest;
use App\Modules\Recruitment\Interfaces\Http\Resources\ApplicationResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TransitionApplicationController extends Controller
{
    public function __invoke(
        TransitionApplicationRequest $request,
        TransitionApplication $transitionApplication,
    ): ApplicationResource|JsonResponse {
        try {
            return new ApplicationResource($transitionApplication->handle(
                $request->tenantContext(),
                $request->applicationId(),
                $request->targetStatus(),
                $request->note(),
            ));
        } catch (ApplicationNotFound) {
            throw new NotFoundHttpException('Not Found');
        } catch (ApplicationTransitionForbidden) {
            return response()->json(
                ['message' => 'Your Organisation role cannot perform this application transition.'],
                Response::HTTP_FORBIDDEN,
            );
        } catch (ApplicationTransitionNotAllowed) {
            return response()->json(
                ['message' => 'The requested application status transition is not allowed.'],
                Response::HTTP_CONFLICT,
            );
        }
    }
}
