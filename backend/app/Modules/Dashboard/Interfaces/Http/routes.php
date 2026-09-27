<?php

use App\Modules\Dashboard\Interfaces\Http\Controllers\ShowDashboardController;
use App\Modules\Organisation\Interfaces\Http\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', ResolveTenantContext::class])
    ->get('/organisations/{organisation}/dashboard', ShowDashboardController::class)
    ->whereNumber('organisation')
    ->name('api.v1.dashboard.show');
