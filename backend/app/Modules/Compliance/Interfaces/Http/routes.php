<?php

use App\Modules\Compliance\Interfaces\Http\Controllers\CreateQualificationDefinitionController;
use App\Modules\Compliance\Interfaces\Http\Controllers\ListQualificationDefinitionsController;
use App\Modules\Compliance\Interfaces\Http\Controllers\ListQualificationExpiriesController;
use App\Modules\Compliance\Interfaces\Http\Controllers\RequestExpiryDigestController;
use App\Modules\Compliance\Interfaces\Http\Controllers\ShowExpiryDigestRequestController;
use App\Modules\Compliance\Interfaces\Http\Controllers\ShowQualificationCoverageController;
use App\Modules\Compliance\Interfaces\Http\Controllers\UpdateQualificationDefinitionController;
use App\Modules\Organisation\Interfaces\Http\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', ResolveTenantContext::class])
    ->prefix('/organisations/{organisation}')
    ->whereNumber('organisation')
    ->group(function (): void {
        Route::get('/qualifications', ListQualificationDefinitionsController::class);
        Route::post('/qualifications', CreateQualificationDefinitionController::class);
        Route::patch('/qualifications/{qualification}', UpdateQualificationDefinitionController::class)->whereNumber('qualification');
        Route::get('/jobs/{job}/candidates/{candidate}/qualification-coverage', ShowQualificationCoverageController::class)->whereNumber(['job', 'candidate']);
        Route::get('/qualification-expiries', ListQualificationExpiriesController::class);
        Route::post('/compliance/expiry-digests', RequestExpiryDigestController::class)
            ->middleware('public-demo.restrict:expiry-digest')
            ->name('api.v1.compliance.expiry-digests.store');
        Route::get('/compliance/expiry-digests/{digestRequest}', ShowExpiryDigestRequestController::class)
            ->whereNumber('digestRequest')
            ->name('api.v1.compliance.expiry-digests.show');
    });
