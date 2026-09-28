<?php

namespace App\Modules\Compliance\Application\Contracts;

use App\Modules\Compliance\Application\Data\QualificationExpiryRecord;
use DateTimeImmutable;

interface QualificationExpiryReadModel
{
    /** @return list<QualificationExpiryRecord> */
    public function expiringBy(int $organisationId, DateTimeImmutable $today, DateTimeImmutable $warningBoundary): array;
}
