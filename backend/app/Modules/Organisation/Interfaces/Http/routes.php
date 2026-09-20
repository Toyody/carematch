<?php

use App\Modules\Organisation\Interfaces\Http\Controllers\CreateOrganisationController;
use App\Modules\Organisation\Interfaces\Http\Controllers\ListOrganisationsController;
use App\Modules\Organisation\Interfaces\Http\Controllers\ShowOrganisationController;
use App\Modules\Organisation\Interfaces\Http\Controllers\UpdateOrganisationController;
use App\Modules\Organisation\Interfaces\Http\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/organisations', ListOrganisationsController::class)
        ->name('api.v1.organisations.index');
    Route::post('/organisations', CreateOrganisationController::class)
        ->name('api.v1.organisations.store');

    Route::middleware(ResolveTenantContext::class)
        ->group(function (): void {
            Route::get('/organisations/{organisation}', ShowOrganisationController::class)
                ->name('api.v1.organisations.show')
                ->whereNumber('organisation');
            Route::patch('/organisations/{organisation}', UpdateOrganisationController::class)
                ->name('api.v1.organisations.update')
                ->whereNumber('organisation');
        });
});
