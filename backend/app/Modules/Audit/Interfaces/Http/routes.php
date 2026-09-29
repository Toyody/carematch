<?php

use App\Modules\Audit\Interfaces\Http\Controllers\ListAuditEventsController;
use App\Modules\Organisation\Interfaces\Http\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', ResolveTenantContext::class])->group(function (): void {
    Route::get('/organisations/{organisation}/audit-events', ListAuditEventsController::class)
        ->name('api.v1.audit-events.index')
        ->whereNumber('organisation');
});
