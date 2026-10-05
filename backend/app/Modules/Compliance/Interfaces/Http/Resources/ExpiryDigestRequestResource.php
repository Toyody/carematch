<?php

namespace App\Modules\Compliance\Interfaces\Http\Resources;

use App\Modules\Compliance\Application\Data\ExpiryDigestRequestRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExpiryDigestRequestRecord */
final class ExpiryDigestRequestResource extends JsonResource
{
    /** @return array<string, int|string|null> */
    public function toArray(Request $request): array
    {
        /** @var ExpiryDigestRequestRecord $digest */
        $digest = $this->resource;

        return [
            'id' => $digest->id,
            'status' => $digest->status->value,
            'expired_count' => $digest->expiredCount,
            'expiring_count' => $digest->expiringCount,
            'failure_code' => $digest->failureCode,
            'queued_at' => $digest->queuedAt->format(DATE_ATOM),
            'processing_started_at' => $digest->processingStartedAt?->format(DATE_ATOM),
            'sent_at' => $digest->sentAt?->format(DATE_ATOM),
            'failed_at' => $digest->failedAt?->format(DATE_ATOM),
        ];
    }
}
