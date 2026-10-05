<?php

namespace App\Modules\Compliance\Application\Data;

final readonly class ExpiryDigestSummary
{
    public function __construct(
        public int $expiredCount,
        public int $expiringCount,
        public int $warningDays,
    ) {}
}
