<?php

namespace App\Modules\Recruitment\Infrastructure\Providers;

use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Recruitment\Application\Contracts\ApplicationCreator;
use App\Modules\Recruitment\Application\Contracts\ApplicationDetails;
use App\Modules\Recruitment\Application\Contracts\ApplicationLister;
use App\Modules\Recruitment\Application\Contracts\JobCreator;
use App\Modules\Recruitment\Application\Contracts\JobDetails;
use App\Modules\Recruitment\Application\Contracts\JobLifecycleTransitioner;
use App\Modules\Recruitment\Application\Contracts\JobLister;
use App\Modules\Recruitment\Application\Contracts\JobUpdater;
use App\Modules\Recruitment\Infrastructure\Persistence\EloquentApplicationCreator;
use App\Modules\Recruitment\Infrastructure\Persistence\EloquentApplicationDetails;
use App\Modules\Recruitment\Infrastructure\Persistence\EloquentApplicationLister;
use App\Modules\Recruitment\Infrastructure\Persistence\EloquentJobCreator;
use App\Modules\Recruitment\Infrastructure\Persistence\EloquentJobDetails;
use App\Modules\Recruitment\Infrastructure\Persistence\EloquentJobLifecycleTransitioner;
use App\Modules\Recruitment\Infrastructure\Persistence\EloquentJobLister;
use App\Modules\Recruitment\Infrastructure\Persistence\EloquentJobUpdater;
use App\Modules\Recruitment\Interfaces\Authorization\ApplicationPolicy;
use App\Modules\Recruitment\Interfaces\Authorization\JobPolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class RecruitmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ApplicationCreator::class, EloquentApplicationCreator::class);
        $this->app->bind(ApplicationLister::class, EloquentApplicationLister::class);
        $this->app->bind(ApplicationDetails::class, EloquentApplicationDetails::class);
        $this->app->bind(JobCreator::class, EloquentJobCreator::class);
        $this->app->bind(JobLister::class, EloquentJobLister::class);
        $this->app->bind(JobDetails::class, EloquentJobDetails::class);
        $this->app->bind(JobUpdater::class, EloquentJobUpdater::class);
        $this->app->bind(JobLifecycleTransitioner::class, EloquentJobLifecycleTransitioner::class);
    }

    public function boot(JobPolicy $policy, ApplicationPolicy $applicationPolicy): void
    {
        Gate::define(ApplicationPolicy::VIEW, static fn (Authenticatable $user, TenantContext $tenant): bool => $applicationPolicy->view($user, $tenant));
        Gate::define(ApplicationPolicy::CREATE, static fn (Authenticatable $user, TenantContext $tenant): bool => $applicationPolicy->create($user, $tenant));
        Gate::define(JobPolicy::VIEW, static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->view($user, $tenant));
        Gate::define(JobPolicy::CREATE, static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->write($user, $tenant));
        Gate::define(JobPolicy::UPDATE, static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->write($user, $tenant));
        Gate::define(JobPolicy::TRANSITION, static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->write($user, $tenant));
    }
}
