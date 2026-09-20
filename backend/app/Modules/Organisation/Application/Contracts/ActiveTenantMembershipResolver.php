<?php

namespace App\Modules\Organisation\Application\Contracts;

use App\Modules\Organisation\Application\Data\TenantContext;

interface ActiveTenantMembershipResolver
{
    public function resolve(int $userId, int $organisationId): ?TenantContext;
}
