<?php

namespace App\Modules\Compliance\Application\Contracts;

use App\Modules\Compliance\Application\Data\ExpiryDigestRequestRecord;
use App\Modules\Compliance\Application\Data\ExpiryDigestRequestResult;
use App\Modules\Compliance\Application\Data\ExpiryDigestSummary;
use App\Modules\Compliance\Application\Data\ExpiryDigestWork;
use App\Modules\Organisation\Application\Data\TenantContext;

interface ExpiryDigestRequestStore
{
    public function createOrRetrieve(TenantContext $tenant, string $keyHash, string $fingerprint): ExpiryDigestRequestResult;

    public function findForOrganisation(int $organisationId, int $requestId): ?ExpiryDigestRequestRecord;

    public function beginProcessing(int $requestId): ?ExpiryDigestWork;

    public function markSent(int $requestId, ExpiryDigestSummary $summary): void;

    public function markFailed(int $requestId, string $failureCode): void;
}
