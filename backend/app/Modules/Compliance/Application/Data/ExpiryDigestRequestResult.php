<?php

namespace App\Modules\Compliance\Application\Data;

final readonly class ExpiryDigestRequestResult
{
    public function __construct(
        public ExpiryDigestRequestRecord $request,
        public bool $created,
    ) {}
}
