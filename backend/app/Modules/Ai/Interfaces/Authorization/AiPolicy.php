<?php

namespace App\Modules\Ai\Interfaces\Authorization;

use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;

final class AiPolicy
{
    public const string USE = 'ai.use';

    public const string VIEW = 'ai.view';

    public function view(Authenticatable $user, TenantContext $tenant): bool
    {
        return $user->getAuthIdentifier() === $tenant->userId;
    }

    public function use(Authenticatable $user, TenantContext $tenant): bool
    {
        return $this->view($user, $tenant)
            && in_array($tenant->role, [OrganisationRole::Admin, OrganisationRole::Recruiter], true);
    }
}
