<?php

namespace App\Modules\Organisation\Infrastructure\Providers;

use App\Modules\Organisation\Application\Actions\CreateOrganisationInvitation;
use App\Modules\Organisation\Application\Contracts\ActiveOrganisationMemberships;
use App\Modules\Organisation\Application\Contracts\ActiveTenantMembershipResolver;
use App\Modules\Organisation\Application\Contracts\OrganisationCreator;
use App\Modules\Organisation\Application\Contracts\OrganisationDetails;
use App\Modules\Organisation\Application\Contracts\OrganisationInvitationAcceptor;
use App\Modules\Organisation\Application\Contracts\OrganisationInvitationCreator;
use App\Modules\Organisation\Application\Contracts\OrganisationInvitationLister;
use App\Modules\Organisation\Application\Contracts\OrganisationInvitationNotifier;
use App\Modules\Organisation\Application\Contracts\OrganisationInvitationRevoker;
use App\Modules\Organisation\Application\Contracts\OrganisationNameUpdater;
use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Organisation\Infrastructure\Notifications\LaravelOrganisationInvitationNotifier;
use App\Modules\Organisation\Infrastructure\Persistence\EloquentActiveOrganisationMemberships;
use App\Modules\Organisation\Infrastructure\Persistence\EloquentActiveTenantMembershipResolver;
use App\Modules\Organisation\Infrastructure\Persistence\EloquentOrganisationCreator;
use App\Modules\Organisation\Infrastructure\Persistence\EloquentOrganisationDetails;
use App\Modules\Organisation\Infrastructure\Persistence\EloquentOrganisationInvitationAcceptor;
use App\Modules\Organisation\Infrastructure\Persistence\EloquentOrganisationInvitationCreator;
use App\Modules\Organisation\Infrastructure\Persistence\EloquentOrganisationInvitationLister;
use App\Modules\Organisation\Infrastructure\Persistence\EloquentOrganisationInvitationRevoker;
use App\Modules\Organisation\Infrastructure\Persistence\EloquentOrganisationNameUpdater;
use App\Modules\Organisation\Interfaces\Authorization\OrganisationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class OrganisationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            OrganisationCreator::class,
            EloquentOrganisationCreator::class,
        );

        $this->app->bind(
            ActiveOrganisationMemberships::class,
            EloquentActiveOrganisationMemberships::class,
        );

        $this->app->bind(
            ActiveTenantMembershipResolver::class,
            EloquentActiveTenantMembershipResolver::class,
        );

        $this->app->bind(
            OrganisationDetails::class,
            EloquentOrganisationDetails::class,
        );

        $this->app->bind(
            OrganisationNameUpdater::class,
            EloquentOrganisationNameUpdater::class,
        );

        $this->app->bind(
            OrganisationInvitationCreator::class,
            EloquentOrganisationInvitationCreator::class,
        );

        $this->app->bind(
            OrganisationInvitationLister::class,
            EloquentOrganisationInvitationLister::class,
        );

        $this->app->bind(
            OrganisationInvitationRevoker::class,
            EloquentOrganisationInvitationRevoker::class,
        );

        $this->app->bind(
            OrganisationInvitationAcceptor::class,
            EloquentOrganisationInvitationAcceptor::class,
        );

        $this->app->bind(
            OrganisationInvitationNotifier::class,
            LaravelOrganisationInvitationNotifier::class,
        );

        $this->app->when(CreateOrganisationInvitation::class)
            ->needs('$expiryDays')
            ->give(static fn (): int => (int) config('carematch.invitation_expiry_days', 7));
    }

    public function boot(): void
    {
        Gate::policy(TenantContext::class, OrganisationPolicy::class);
    }
}
