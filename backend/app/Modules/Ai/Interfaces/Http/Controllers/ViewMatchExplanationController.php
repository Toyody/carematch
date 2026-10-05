<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Application\Actions\ViewMatchExplanation;
use App\Modules\Ai\Application\Exceptions\AiResourceNotFound;
use App\Modules\Ai\Interfaces\Http\Requests\ViewAiRequest;
use App\Modules\Ai\Interfaces\Http\Resources\MatchExplanationResource;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ViewMatchExplanationController extends Controller
{
    public function __invoke(ViewAiRequest $request, ViewMatchExplanation $action): MatchExplanationResource
    {
        try {
            $view = $action->handle(
                $request->tenantContext(), $request->routeId('job'), $request->routeId('candidate'), $request->routeId('explanation'),
                CarbonImmutable::today('UTC'), (int) config('carematch.compliance.expiry_warning_days', 30),
            );
        } catch (AiResourceNotFound) {
            throw new NotFoundHttpException('Not Found');
        }

        return new MatchExplanationResource($view);
    }
}
