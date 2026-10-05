<?php

namespace App\Modules\Compliance\Application\Contracts;

interface ExpiryDigestDispatcher
{
    public function dispatchAfterCommit(int $requestId): void;
}
