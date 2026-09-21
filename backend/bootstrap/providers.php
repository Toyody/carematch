<?php

use App\Modules\Candidate\Infrastructure\Providers\CandidateServiceProvider;
use App\Modules\Identity\Infrastructure\Providers\IdentityServiceProvider;
use App\Modules\Organisation\Infrastructure\Providers\OrganisationServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    OrganisationServiceProvider::class,
    CandidateServiceProvider::class,
];
