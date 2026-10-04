<?php

namespace App\Modules\Matching\Infrastructure\Providers;

use App\Modules\Matching\Application\Contracts\CandidateMatchReadModel;
use App\Modules\Matching\Infrastructure\Persistence\PostgreSqlCandidateMatchReadModel;
use App\Modules\Matching\Interfaces\Authorization\MatchingPolicy;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class MatchingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CandidateMatchReadModel::class, PostgreSqlCandidateMatchReadModel::class);
    }

    public function boot(MatchingPolicy $policy): void
    {
        Gate::define(
            MatchingPolicy::VIEW,
            static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->view($user, $tenant),
        );
    }
}
