<?php

use App\Modules\Organisation\Interfaces\Http\Controllers\AcceptOrganisationInvitationController;
use App\Modules\Organisation\Interfaces\Http\Controllers\CreateOrganisationController;
use App\Modules\Organisation\Interfaces\Http\Controllers\CreateOrganisationInvitationController;
use App\Modules\Organisation\Interfaces\Http\Controllers\ListOrganisationInvitationsController;
use App\Modules\Organisation\Interfaces\Http\Controllers\ListOrganisationsController;
use App\Modules\Organisation\Interfaces\Http\Controllers\RevokeOrganisationInvitationController;
use App\Modules\Organisation\Interfaces\Http\Controllers\ShowOrganisationController;
use App\Modules\Organisation\Interfaces\Http\Controllers\UpdateOrganisationController;
use App\Modules\Organisation\Interfaces\Http\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/organisation-invitations/accept', AcceptOrganisationInvitationController::class)
        ->name('api.v1.organisation-invitations.accept');

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
            Route::get('/organisations/{organisation}/invitations', ListOrganisationInvitationsController::class)
                ->name('api.v1.organisation-invitations.index')
                ->whereNumber('organisation');
            Route::post('/organisations/{organisation}/invitations', CreateOrganisationInvitationController::class)
                ->name('api.v1.organisation-invitations.store')
                ->whereNumber('organisation');
            Route::post('/organisations/{organisation}/invitations/{invitation}/revoke', RevokeOrganisationInvitationController::class)
                ->name('api.v1.organisation-invitations.revoke')
                ->whereNumber('organisation')
                ->whereNumber('invitation');
        });
});
