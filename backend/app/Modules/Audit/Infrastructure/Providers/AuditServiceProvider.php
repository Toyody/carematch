<?php

namespace App\Modules\Audit\Infrastructure\Providers;

use App\Modules\Audit\Application\Contracts\AuditEventReader;
use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Infrastructure\Persistence\PostgreSqlAuditEventReader;
use App\Modules\Audit\Infrastructure\Persistence\PostgreSqlAuditRecorder;
use App\Modules\Audit\Interfaces\Authorization\AuditPolicy;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuditRecorder::class, PostgreSqlAuditRecorder::class);
        $this->app->bind(AuditEventReader::class, PostgreSqlAuditEventReader::class);
    }

    public function boot(): void
    {
        Gate::define(AuditPolicy::VIEW, static fn ($user, TenantContext $tenant): bool => (new AuditPolicy)->view($user, $tenant));
    }
}
