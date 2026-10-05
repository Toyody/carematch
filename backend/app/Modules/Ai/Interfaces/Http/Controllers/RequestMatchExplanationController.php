<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Application\Actions\RequestMatchExplanation;
use App\Modules\Ai\Application\Exceptions\AiDisabled;
use App\Modules\Ai\Application\Exceptions\AiResourceNotFound;
use App\Modules\Ai\Interfaces\Http\Requests\RequestMatchExplanationRequest;
use App\Modules\Ai\Interfaces\Http\Resources\MatchExplanationResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RequestMatchExplanationController extends Controller
{
    public function __invoke(RequestMatchExplanationRequest $request, RequestMatchExplanation $action): JsonResponse
    {
        try {
            $record = $action->handle(
                $request->tenantContext(), $request->routeId('job'), $request->routeId('candidate'),
                CarbonImmutable::today('UTC'), (int) config('carematch.compliance.expiry_warning_days', 30),
            );
        } catch (AiResourceNotFound) {
            throw new NotFoundHttpException('Not Found');
        } catch (AiDisabled) {
            return new JsonResponse(['message' => 'AI assistance is not enabled.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return (new MatchExplanationResource($record))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
