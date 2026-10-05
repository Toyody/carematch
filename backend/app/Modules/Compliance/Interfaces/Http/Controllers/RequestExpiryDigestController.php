<?php

namespace App\Modules\Compliance\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Application\Actions\RequestExpiryDigest;
use App\Modules\Compliance\Application\Exceptions\ExpiryDigestIdempotencyConflict;
use App\Modules\Compliance\Interfaces\Http\Requests\RequestExpiryDigestRequest;
use App\Modules\Compliance\Interfaces\Http\Resources\ExpiryDigestRequestResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class RequestExpiryDigestController extends Controller
{
    public function __invoke(RequestExpiryDigestRequest $request, RequestExpiryDigest $action): JsonResponse
    {
        try {
            return (new ExpiryDigestRequestResource($action->handle(
                $request->tenantContext(),
                $request->idempotencyKey(),
            )))->response()->setStatusCode(Response::HTTP_ACCEPTED);
        } catch (ExpiryDigestIdempotencyConflict) {
            return new JsonResponse([
                'message' => 'The Idempotency-Key has already been used for a different request.',
            ], Response::HTTP_CONFLICT);
        }
    }
}
