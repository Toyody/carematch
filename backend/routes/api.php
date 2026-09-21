<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class)->name('api.v1.health');
});

Route::prefix('v1')->group(
    base_path('app/Modules/Identity/Interfaces/Http/routes.php'),
);

Route::prefix('v1')->group(
    base_path('app/Modules/Organisation/Interfaces/Http/routes.php'),
);

Route::prefix('v1')->group(
    base_path('app/Modules/Candidate/Interfaces/Http/routes.php'),
);
