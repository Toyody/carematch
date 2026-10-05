<?php

namespace App\Modules\Compliance\Interfaces\Authorization;

use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;

final class CompliancePolicy
{
    public const string VIEW = 'compliance.view';

    public const string MANAGE_CATALOGUE = 'compliance.catalogue.manage';

    public const string REQUEST_EXPIRY_DIGEST = 'compliance.expiry-digest.request';

    public function view(Authenticatable $user, TenantContext $tenant): bool
    {
        return $user->getAuthIdentifier() === $tenant->userId;
    }

    public function manageCatalogue(Authenticatable $user, TenantContext $tenant): bool
    {
        return $this->view($user, $tenant) && $tenant->role === OrganisationRole::Admin;
    }

    public function requestExpiryDigest(Authenticatable $user, TenantContext $tenant): bool
    {
        return $this->view($user, $tenant) && $tenant->role === OrganisationRole::Admin;
    }
}
