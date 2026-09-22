<?php

namespace App\Modules\Candidate\Infrastructure\Providers;

use App\Modules\Candidate\Application\Contracts\CandidateCreator;
use App\Modules\Candidate\Application\Contracts\CandidateDetails;
use App\Modules\Candidate\Application\Contracts\CandidateDocumentStorage;
use App\Modules\Candidate\Application\Contracts\CandidateDocumentStore;
use App\Modules\Candidate\Application\Contracts\CandidateLister;
use App\Modules\Candidate\Application\Contracts\CandidateReferenceLookup;
use App\Modules\Candidate\Application\Contracts\CandidateUpdater;
use App\Modules\Candidate\Infrastructure\Persistence\EloquentCandidateCreator;
use App\Modules\Candidate\Infrastructure\Persistence\EloquentCandidateDetails;
use App\Modules\Candidate\Infrastructure\Persistence\EloquentCandidateDocumentStore;
use App\Modules\Candidate\Infrastructure\Persistence\EloquentCandidateLister;
use App\Modules\Candidate\Infrastructure\Persistence\EloquentCandidateReferenceLookup;
use App\Modules\Candidate\Infrastructure\Persistence\EloquentCandidateUpdater;
use App\Modules\Candidate\Infrastructure\Storage\LaravelCandidateDocumentStorage;
use App\Modules\Candidate\Interfaces\Authorization\CandidatePolicy;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class CandidateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CandidateCreator::class, EloquentCandidateCreator::class);
        $this->app->bind(CandidateLister::class, EloquentCandidateLister::class);
        $this->app->bind(CandidateDetails::class, EloquentCandidateDetails::class);
        $this->app->bind(CandidateUpdater::class, EloquentCandidateUpdater::class);
        $this->app->bind(CandidateReferenceLookup::class, EloquentCandidateReferenceLookup::class);
        $this->app->bind(CandidateDocumentStore::class, EloquentCandidateDocumentStore::class);
        $this->app->bind(CandidateDocumentStorage::class, LaravelCandidateDocumentStorage::class);
    }

    public function boot(CandidatePolicy $policy): void
    {
        Gate::define(
            CandidatePolicy::VIEW,
            static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->view($user, $tenant),
        );
        Gate::define(
            CandidatePolicy::CREATE,
            static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->create($user, $tenant),
        );
        Gate::define(
            CandidatePolicy::UPDATE,
            static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->update($user, $tenant),
        );
        Gate::define(
            CandidatePolicy::VIEW_DOCUMENTS,
            static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->viewDocuments($user, $tenant),
        );
        Gate::define(
            CandidatePolicy::MANAGE_DOCUMENTS,
            static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->manageDocuments($user, $tenant),
        );
    }
}
