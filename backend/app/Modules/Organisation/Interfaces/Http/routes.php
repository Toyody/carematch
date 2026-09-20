<?php

use App\Modules\Organisation\Interfaces\Http\Controllers\CreateOrganisationController;
use App\Modules\Organisation\Interfaces\Http\Controllers\ListOrganisationsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/organisations', ListOrganisationsController::class)
        ->name('api.v1.organisations.index');
    Route::post('/organisations', CreateOrganisationController::class)
        ->name('api.v1.organisations.store');
});
