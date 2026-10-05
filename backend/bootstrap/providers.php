<?php

use App\Modules\Ai\Infrastructure\Providers\AiServiceProvider;
use App\Modules\Analytics\Infrastructure\Providers\AnalyticsServiceProvider;
use App\Modules\Audit\Infrastructure\Providers\AuditServiceProvider;
use App\Modules\Candidate\Infrastructure\Providers\CandidateServiceProvider;
use App\Modules\Compliance\Infrastructure\Providers\ComplianceServiceProvider;
use App\Modules\Dashboard\Infrastructure\Providers\DashboardServiceProvider;
use App\Modules\Identity\Infrastructure\Providers\IdentityServiceProvider;
use App\Modules\Matching\Infrastructure\Providers\MatchingServiceProvider;
use App\Modules\Organisation\Infrastructure\Providers\OrganisationServiceProvider;
use App\Modules\Recruitment\Infrastructure\Providers\RecruitmentServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    AiServiceProvider::class,
    AnalyticsServiceProvider::class,
    AuditServiceProvider::class,
    IdentityServiceProvider::class,
    OrganisationServiceProvider::class,
    CandidateServiceProvider::class,
    ComplianceServiceProvider::class,
    RecruitmentServiceProvider::class,
    MatchingServiceProvider::class,
    DashboardServiceProvider::class,
];
