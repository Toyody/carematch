<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Application\Actions\ViewCvExtraction;
use App\Modules\Ai\Application\Exceptions\AiResourceNotFound;
use App\Modules\Ai\Interfaces\Http\Requests\ViewCvExtractionRequest;
use App\Modules\Ai\Interfaces\Http\Resources\CvExtractionResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ViewCvExtractionController extends Controller
{
    public function __invoke(ViewCvExtractionRequest $request, ViewCvExtraction $action): CvExtractionResource
    {
        try {
            $record = $action->handle($request->tenantContext(), $request->routeId('candidate'), $request->routeId('document'), $request->routeId('extraction'));
        } catch (AiResourceNotFound) {
            throw new NotFoundHttpException('Not Found');
        }

        return new CvExtractionResource($record);
    }
}
