<?php

namespace App\Modules\Compliance\Application\Actions;

use App\Modules\Compliance\Application\Contracts\ExpiryDigestDispatcher;
use App\Modules\Compliance\Application\Contracts\ExpiryDigestRequestStore;
use App\Modules\Compliance\Application\Data\ExpiryDigestRequestRecord;
use App\Modules\Compliance\Domain\ExpiryDigestStatus;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class RequestExpiryDigest
{
    private const string FINGERPRINT = 'compliance.expiry-digest:v1:{}';

    public function __construct(
        private ExpiryDigestRequestStore $store,
        private ExpiryDigestDispatcher $dispatcher,
    ) {}

    public function handle(TenantContext $tenant, string $idempotencyKey): ExpiryDigestRequestRecord
    {
        $result = $this->store->createOrRetrieve(
            $tenant,
            hash('sha256', $idempotencyKey),
            hash('sha256', self::FINGERPRINT),
        );

        if ($result->request->status === ExpiryDigestStatus::Queued) {
            $this->dispatcher->dispatchAfterCommit($result->request->id);
        }

        return $result->request;
    }
}
