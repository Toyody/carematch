<?php

namespace App\Modules\Compliance\Application\Actions;

use App\Modules\Compliance\Application\Contracts\ExpiryDigestRequestStore;
use App\Modules\Compliance\Application\Data\ExpiryDigestRequestRecord;
use App\Modules\Compliance\Application\Exceptions\ExpiryDigestRequestNotFound;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class ViewExpiryDigestRequest
{
    public function __construct(private ExpiryDigestRequestStore $store) {}

    public function handle(TenantContext $tenant, int $requestId): ExpiryDigestRequestRecord
    {
        return $this->store->findForOrganisation($tenant->organisationId, $requestId)
            ?? throw new ExpiryDigestRequestNotFound;
    }
}
