<?php

namespace App\Modules\Analytics\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Application\Actions\ViewAnalytics;
use App\Modules\Analytics\Interfaces\Http\Requests\ViewAnalyticsRequest;
use App\Modules\Analytics\Interfaces\Http\Resources\AnalyticsResource;

final class ShowAnalyticsController extends Controller
{
    public function __invoke(ViewAnalyticsRequest $request, ViewAnalytics $viewAnalytics): AnalyticsResource
    {
        return new AnalyticsResource($viewAnalytics->handle($request->tenantContext(), $request->period()));
    }
}
