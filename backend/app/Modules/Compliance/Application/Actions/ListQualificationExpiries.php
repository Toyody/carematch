<?php

namespace App\Modules\Compliance\Application\Actions;

use App\Modules\Compliance\Application\Contracts\QualificationExpiryReadModel;
use App\Modules\Compliance\Application\Data\QualificationExpiryRecord;
use App\Modules\Organisation\Application\Data\TenantContext;
use DateTimeImmutable;

final readonly class ListQualificationExpiries
{
    public function __construct(private QualificationExpiryReadModel $readModel) {}

    /** @return list<QualificationExpiryRecord> */
    public function handle(TenantContext $tenant, DateTimeImmutable $today, int $warningDays): array
    {
        return $this->readModel->expiringBy($tenant->organisationId, $today, $today->modify(sprintf('+%d days', $warningDays)));
    }
}
