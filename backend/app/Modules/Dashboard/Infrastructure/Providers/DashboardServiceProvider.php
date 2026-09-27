<?php

namespace App\Modules\Dashboard\Infrastructure\Providers;

use App\Modules\Dashboard\Application\Contracts\DashboardReadModel;
use App\Modules\Dashboard\Infrastructure\Persistence\PostgreSqlDashboardReadModel;
use Illuminate\Support\ServiceProvider;

final class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DashboardReadModel::class, PostgreSqlDashboardReadModel::class);
    }
}
