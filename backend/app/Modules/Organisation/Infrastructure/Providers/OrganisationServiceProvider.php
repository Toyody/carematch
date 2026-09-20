<?php

namespace App\Modules\Organisation\Infrastructure\Providers;

use App\Modules\Organisation\Application\Contracts\ActiveOrganisationMemberships;
use App\Modules\Organisation\Application\Contracts\OrganisationCreator;
use App\Modules\Organisation\Infrastructure\Persistence\EloquentActiveOrganisationMemberships;
use App\Modules\Organisation\Infrastructure\Persistence\EloquentOrganisationCreator;
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
    }
}
