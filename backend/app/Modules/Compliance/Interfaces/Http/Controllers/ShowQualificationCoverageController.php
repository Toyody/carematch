<?php

namespace App\Modules\Compliance\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Application\Actions\EvaluateQualificationCoverage;
use App\Modules\Compliance\Application\Exceptions\QualificationCoverageTargetNotFound;
use App\Modules\Compliance\Interfaces\Http\Requests\ListQualificationDefinitionsRequest;
use App\Modules\Compliance\Interfaces\Http\Resources\QualificationCoverageResource;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShowQualificationCoverageController extends Controller
{
    public function __invoke(ListQualificationDefinitionsRequest $request, EvaluateQualificationCoverage $action): QualificationCoverageResource
    {
        try {
            return new QualificationCoverageResource($action->handle(
                $request->tenantContext(),
                $request->routeId('job'),
                $request->routeId('candidate'),
                CarbonImmutable::today('UTC'),
                max(0, (int) config('carematch.compliance.expiry_warning_days', 30)),
            ));
        } catch (QualificationCoverageTargetNotFound) {
            throw new NotFoundHttpException('Not Found');
        }
    }
}
