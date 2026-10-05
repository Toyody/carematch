<?php

namespace App\Modules\Compliance\Application\Contracts;

use App\Modules\Compliance\Application\Data\ExpiryDigestSummary;

interface ExpiryDigestNotifier
{
    public function send(int $organisationId, int $requestedByUserId, ExpiryDigestSummary $summary): void;
}
