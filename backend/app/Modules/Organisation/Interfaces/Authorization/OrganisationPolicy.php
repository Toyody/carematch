<?php

namespace App\Modules\Organisation\Interfaces\Authorization;

use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;

final class OrganisationPolicy
{
    public function view(Authenticatable $user, TenantContext $tenant): bool
    {
        return $this->matchesAuthenticatedUser($user, $tenant);
    }

    public function update(Authenticatable $user, TenantContext $tenant): bool
    {
        return $this->matchesAuthenticatedUser($user, $tenant)
            && $tenant->role === OrganisationRole::Admin;
    }

    public function manageInvitations(Authenticatable $user, TenantContext $tenant): bool
    {
        return $this->matchesAuthenticatedUser($user, $tenant)
            && $tenant->role === OrganisationRole::Admin;
    }

    public function manageMemberships(Authenticatable $user, TenantContext $tenant): bool
    {
        return $this->matchesAuthenticatedUser($user, $tenant)
            && $tenant->role === OrganisationRole::Admin;
    }

    private function matchesAuthenticatedUser(Authenticatable $user, TenantContext $tenant): bool
    {
        return $user->getAuthIdentifier() === $tenant->userId;
    }
}
