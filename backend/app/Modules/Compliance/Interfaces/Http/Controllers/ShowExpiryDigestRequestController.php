<?php

namespace App\Modules\Compliance\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Application\Actions\ViewExpiryDigestRequest;
use App\Modules\Compliance\Application\Exceptions\ExpiryDigestRequestNotFound;
use App\Modules\Compliance\Interfaces\Http\Requests\ViewExpiryDigestRequestRequest;
use App\Modules\Compliance\Interfaces\Http\Resources\ExpiryDigestRequestResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShowExpiryDigestRequestController extends Controller
{
    public function __invoke(ViewExpiryDigestRequestRequest $request, ViewExpiryDigestRequest $action): ExpiryDigestRequestResource
    {
        try {
            return new ExpiryDigestRequestResource($action->handle(
                $request->tenantContext(),
                $request->routeId('digestRequest'),
            ));
        } catch (ExpiryDigestRequestNotFound) {
            throw new NotFoundHttpException('Not Found');
        }
    }
}
