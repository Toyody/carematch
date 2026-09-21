<?php

namespace App\Modules\Recruitment\Interfaces\Authorization;

use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;

final class JobPolicy
{
    public const string VIEW = 'jobs.view';

    public const string CREATE = 'jobs.create';

    public const string UPDATE = 'jobs.update';

    public const string TRANSITION = 'jobs.transition';

    public function view(Authenticatable $user, TenantContext $tenant): bool
    {
        return $user->getAuthIdentifier() === $tenant->userId;
    }

    public function write(Authenticatable $user, TenantContext $tenant): bool
    {
        return $this->view($user, $tenant) && in_array($tenant->role, [
            OrganisationRole::Admin,
            OrganisationRole::Recruiter,
        ], true);
    }
}
