<?php

namespace App\Modules\Organisation\Application\Data;

final readonly class TenantContext
{
    public function __construct(
        public int $organisationId,
        public int $userId,
        public int $membershipId,
        public OrganisationRole $role,
    ) {}
}
