<?php

namespace App\Modules\Audit\Interfaces\Authorization;

use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;

final class AuditPolicy
{
    public const string VIEW = 'viewAuditEvents';

    public function view(Authenticatable $user, TenantContext $tenant): bool
    {
        return $user->getAuthIdentifier() === $tenant->userId && $tenant->role === OrganisationRole::Admin;
    }
}
