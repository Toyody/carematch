<?php

namespace App\Modules\Compliance\Infrastructure\Providers;

use App\Modules\Compliance\Application\Contracts\ExpiryDigestDispatcher;
use App\Modules\Compliance\Application\Contracts\ExpiryDigestNotifier;
use App\Modules\Compliance\Application\Contracts\ExpiryDigestRequestStore;
use App\Modules\Compliance\Application\Contracts\ExpiryDigestSummaryReadModel;
use App\Modules\Compliance\Application\Contracts\QualificationCoverageReadModel;
use App\Modules\Compliance\Application\Contracts\QualificationDefinitionReferences;
use App\Modules\Compliance\Application\Contracts\QualificationDefinitionStore;
use App\Modules\Compliance\Application\Contracts\QualificationExpiryReadModel;
use App\Modules\Compliance\Infrastructure\Notifications\LaravelExpiryDigestNotifier;
use App\Modules\Compliance\Infrastructure\Persistence\EloquentExpiryDigestRequestStore;
use App\Modules\Compliance\Infrastructure\Persistence\EloquentQualificationDefinitionStore;
use App\Modules\Compliance\Infrastructure\Persistence\PostgreSqlExpiryDigestSummaryReadModel;
use App\Modules\Compliance\Infrastructure\Persistence\PostgreSqlQualificationCoverageReadModel;
use App\Modules\Compliance\Infrastructure\Persistence\PostgreSqlQualificationExpiryReadModel;
use App\Modules\Compliance\Infrastructure\Queue\LaravelExpiryDigestDispatcher;
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
        $this->app->bind(ExpiryDigestRequestStore::class, EloquentExpiryDigestRequestStore::class);
        $this->app->bind(ExpiryDigestSummaryReadModel::class, PostgreSqlExpiryDigestSummaryReadModel::class);
        $this->app->bind(ExpiryDigestNotifier::class, LaravelExpiryDigestNotifier::class);
        $this->app->bind(ExpiryDigestDispatcher::class, LaravelExpiryDigestDispatcher::class);
    }

    public function boot(CompliancePolicy $policy): void
    {
        Gate::define(CompliancePolicy::VIEW, static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->view($user, $tenant));
        Gate::define(CompliancePolicy::MANAGE_CATALOGUE, static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->manageCatalogue($user, $tenant));
        Gate::define(CompliancePolicy::REQUEST_EXPIRY_DIGEST, static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->requestExpiryDigest($user, $tenant));
    }
}
