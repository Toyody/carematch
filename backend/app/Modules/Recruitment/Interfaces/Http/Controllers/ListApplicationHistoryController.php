<?php

namespace App\Modules\Recruitment\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Application\Actions\ViewApplicationHistory;
use App\Modules\Recruitment\Application\Exceptions\ApplicationNotFound;
use App\Modules\Recruitment\Interfaces\Http\Requests\ShowApplicationHistoryRequest;
use App\Modules\Recruitment\Interfaces\Http\Resources\ApplicationStatusHistoryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ListApplicationHistoryController extends Controller
{
    public function __invoke(
        ShowApplicationHistoryRequest $request,
        ViewApplicationHistory $viewHistory,
    ): AnonymousResourceCollection {
        try {
            return ApplicationStatusHistoryResource::collection(
                $viewHistory->handle($request->tenantContext(), $request->applicationId()),
            );
        } catch (ApplicationNotFound) {
            throw new NotFoundHttpException('Not Found');
        }
    }
}
