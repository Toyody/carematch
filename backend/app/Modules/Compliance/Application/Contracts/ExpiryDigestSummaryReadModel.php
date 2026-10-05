<?php

namespace App\Modules\Compliance\Application\Contracts;

use App\Modules\Compliance\Application\Data\ExpiryDigestSummary;
use DateTimeImmutable;

interface ExpiryDigestSummaryReadModel
{
    public function forOrganisation(int $organisationId, DateTimeImmutable $today, int $warningDays): ExpiryDigestSummary;
}
