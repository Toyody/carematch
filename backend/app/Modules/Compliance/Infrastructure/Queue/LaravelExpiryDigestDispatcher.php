<?php

namespace App\Modules\Compliance\Infrastructure\Queue;

use App\Modules\Compliance\Application\Contracts\ExpiryDigestDispatcher;
use App\Modules\Compliance\Infrastructure\Jobs\SendExpiryDigestJob;

final class LaravelExpiryDigestDispatcher implements ExpiryDigestDispatcher
{
    public function dispatchAfterCommit(int $requestId): void
    {
        SendExpiryDigestJob::dispatch($requestId)->afterCommit();
    }
}
