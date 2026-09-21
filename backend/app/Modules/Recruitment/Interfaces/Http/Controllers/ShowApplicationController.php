<?php

namespace App\Modules\Recruitment\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Application\Actions\ViewApplication;
use App\Modules\Recruitment\Application\Exceptions\ApplicationNotFound;
use App\Modules\Recruitment\Interfaces\Http\Requests\ShowApplicationRequest;
use App\Modules\Recruitment\Interfaces\Http\Resources\ApplicationResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShowApplicationController extends Controller
{
    public function __invoke(ShowApplicationRequest $request, ViewApplication $view): ApplicationResource
    {
        try {
            return new ApplicationResource($view->handle($request->tenantContext(), $request->applicationId()));
        } catch (ApplicationNotFound) {
            throw new NotFoundHttpException('Not Found');
        }
    }
}
