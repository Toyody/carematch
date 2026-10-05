<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Application\Actions\RequestCvExtraction;
use App\Modules\Ai\Application\Exceptions\AiDisabled;
use App\Modules\Ai\Application\Exceptions\AiIdempotencyConflict;
use App\Modules\Ai\Application\Exceptions\AiResourceNotFound;
use App\Modules\Ai\Application\Exceptions\PermanentAiFailure;
use App\Modules\Ai\Interfaces\Http\Requests\RequestCvExtractionRequest;
use App\Modules\Ai\Interfaces\Http\Resources\CvExtractionResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RequestCvExtractionController extends Controller
{
    public function __invoke(RequestCvExtractionRequest $request, RequestCvExtraction $action): JsonResponse
    {
        try {
            $record = $action->handle($request->tenantContext(), $request->routeId('candidate'), $request->routeId('document'), $request->idempotencyKey());
        } catch (AiResourceNotFound) {
            throw new NotFoundHttpException('Not Found');
        } catch (AiDisabled) {
            return new JsonResponse(['message' => 'AI assistance is not enabled.'], Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (AiIdempotencyConflict) {
            return new JsonResponse([
                'message' => 'The Idempotency-Key has already been used for a different request.',
                'code' => 'idempotency_conflict',
            ], Response::HTTP_CONFLICT);
        } catch (PermanentAiFailure $exception) {
            return new JsonResponse(['message' => 'This document cannot be processed.', 'code' => $exception->failureCode], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return (new CvExtractionResource($record))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
