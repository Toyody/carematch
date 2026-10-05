<?php

namespace App\Modules\Compliance\Application\Data;

final readonly class ExpiryDigestWork
{
    public function __construct(
        public int $requestId,
        public int $organisationId,
        public int $requestedByUserId,
    ) {}
}
