<?php

namespace App\Modules\Analytics\Infrastructure\Providers;

use App\Modules\Analytics\Application\Contracts\AnalyticsReadModel;
use App\Modules\Analytics\Infrastructure\Persistence\PostgreSqlAnalyticsReadModel;
use App\Modules\Analytics\Interfaces\Authorization\AnalyticsPolicy;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AnalyticsReadModel::class, PostgreSqlAnalyticsReadModel::class);
    }

    public function boot(AnalyticsPolicy $policy): void
    {
        Gate::define(
            AnalyticsPolicy::VIEW,
            static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->view($user, $tenant),
        );
    }
}
