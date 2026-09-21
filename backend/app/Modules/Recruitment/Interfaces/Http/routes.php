<?php

use App\Modules\Organisation\Interfaces\Http\Middleware\ResolveTenantContext;
use App\Modules\Recruitment\Interfaces\Http\Controllers\CreateJobController;
use App\Modules\Recruitment\Interfaces\Http\Controllers\ListJobsController;
use App\Modules\Recruitment\Interfaces\Http\Controllers\ShowJobController;
use App\Modules\Recruitment\Interfaces\Http\Controllers\TransitionJobController;
use App\Modules\Recruitment\Interfaces\Http\Controllers\UpdateJobController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', ResolveTenantContext::class])
    ->prefix('/organisations/{organisation}')
    ->whereNumber('organisation')
    ->group(function (): void {
        Route::get('/jobs', ListJobsController::class)->name('api.v1.jobs.index');
        Route::post('/jobs', CreateJobController::class)->name('api.v1.jobs.store');
        Route::get('/jobs/{job}', ShowJobController::class)->whereNumber('job')->name('api.v1.jobs.show');
        Route::patch('/jobs/{job}', UpdateJobController::class)->whereNumber('job')->name('api.v1.jobs.update');

        foreach (['open', 'close', 'archive'] as $transition) {
            Route::post("/jobs/{job}/{$transition}", TransitionJobController::class)
                ->whereNumber('job')
                ->defaults('transition', $transition === 'close' ? 'closed' : ($transition === 'archive' ? 'archived' : 'open'))
                ->name("api.v1.jobs.{$transition}");
        }
    });
