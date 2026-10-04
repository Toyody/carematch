<?php

use App\Modules\Analytics\Interfaces\Http\Controllers\ShowAnalyticsController;
use App\Modules\Organisation\Interfaces\Http\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', ResolveTenantContext::class])
    ->get('/organisations/{organisation}/analytics', ShowAnalyticsController::class)
    ->whereNumber('organisation')
    ->name('api.v1.analytics.show');
