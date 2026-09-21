<?php

namespace App\Modules\Recruitment\Interfaces\Authorization;

use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;

final class ApplicationPolicy
{
    public const string VIEW = 'applications.view';

    public const string CREATE = 'applications.create';

    public function view(Authenticatable $user, TenantContext $tenant): bool
    {
        return $user->getAuthIdentifier() === $tenant->userId;
    }

    public function create(Authenticatable $user, TenantContext $tenant): bool
    {
        return $this->view($user, $tenant) && in_array($tenant->role, [
            OrganisationRole::Admin,
            OrganisationRole::Recruiter,
        ], true);
    }
}
