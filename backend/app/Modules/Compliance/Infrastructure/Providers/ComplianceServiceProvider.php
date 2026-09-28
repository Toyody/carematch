<?php

namespace App\Modules\Compliance\Infrastructure\Providers;

use App\Modules\Compliance\Application\Contracts\QualificationCoverageReadModel;
use App\Modules\Compliance\Application\Contracts\QualificationDefinitionReferences;
use App\Modules\Compliance\Application\Contracts\QualificationDefinitionStore;
use App\Modules\Compliance\Application\Contracts\QualificationExpiryReadModel;
use App\Modules\Compliance\Infrastructure\Persistence\EloquentQualificationDefinitionStore;
use App\Modules\Compliance\Infrastructure\Persistence\PostgreSqlQualificationCoverageReadModel;
use App\Modules\Compliance\Infrastructure\Persistence\PostgreSqlQualificationExpiryReadModel;
use App\Modules\Compliance\Interfaces\Authorization\CompliancePolicy;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class ComplianceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(QualificationDefinitionStore::class, EloquentQualificationDefinitionStore::class);
        $this->app->bind(QualificationDefinitionReferences::class, EloquentQualificationDefinitionStore::class);
        $this->app->bind(QualificationCoverageReadModel::class, PostgreSqlQualificationCoverageReadModel::class);
        $this->app->bind(QualificationExpiryReadModel::class, PostgreSqlQualificationExpiryReadModel::class);
    }

    public function boot(CompliancePolicy $policy): void
    {
        Gate::define(CompliancePolicy::VIEW, static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->view($user, $tenant));
        Gate::define(CompliancePolicy::MANAGE_CATALOGUE, static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->manageCatalogue($user, $tenant));
    }
}
