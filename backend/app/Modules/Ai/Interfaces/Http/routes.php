<?php

use App\Modules\Ai\Interfaces\Http\Controllers\ApplyCvExtractionController;
use App\Modules\Ai\Interfaces\Http\Controllers\RequestCvExtractionController;
use App\Modules\Ai\Interfaces\Http\Controllers\RequestMatchExplanationController;
use App\Modules\Ai\Interfaces\Http\Controllers\ViewCvExtractionController;
use App\Modules\Ai\Interfaces\Http\Controllers\ViewMatchExplanationController;
use App\Modules\Organisation\Interfaces\Http\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', ResolveTenantContext::class])
    ->prefix('/organisations/{organisation}')
    ->whereNumber('organisation')
    ->group(function (): void {
        Route::post('/candidates/{candidate}/documents/{document}/ai-extractions', RequestCvExtractionController::class)
            ->middleware(['public-demo.restrict:ai-assistance', 'throttle:ai-cv-extraction'])
            ->whereNumber(['candidate', 'document']);
        Route::get('/candidates/{candidate}/documents/{document}/ai-extractions/{extraction}', ViewCvExtractionController::class)
            ->whereNumber(['candidate', 'document', 'extraction']);
        Route::post('/candidates/{candidate}/documents/{document}/ai-extractions/{extraction}/apply', ApplyCvExtractionController::class)
            ->middleware('public-demo.restrict:ai-assistance')
            ->whereNumber(['candidate', 'document', 'extraction']);
        Route::post('/jobs/{job}/matches/{candidate}/ai-explanations', RequestMatchExplanationController::class)
            ->middleware(['public-demo.restrict:ai-assistance', 'throttle:ai-match-explanation'])
            ->whereNumber(['job', 'candidate']);
        Route::get('/jobs/{job}/matches/{candidate}/ai-explanations/{explanation}', ViewMatchExplanationController::class)
            ->whereNumber(['job', 'candidate', 'explanation']);
    });
