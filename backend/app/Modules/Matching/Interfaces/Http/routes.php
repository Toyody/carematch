<?php

use App\Modules\Matching\Interfaces\Http\Controllers\ListCandidateMatchesController;
use App\Modules\Organisation\Interfaces\Http\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', ResolveTenantContext::class])
    ->prefix('/organisations/{organisation}')
    ->whereNumber('organisation')
    ->group(function (): void {
        Route::get('/jobs/{job}/matches', ListCandidateMatchesController::class)
            ->whereNumber('job')
            ->name('api.v1.jobs.matches');
    });
