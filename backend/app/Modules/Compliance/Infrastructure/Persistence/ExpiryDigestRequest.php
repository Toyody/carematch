<?php

namespace App\Modules\Compliance\Infrastructure\Persistence;

use App\Modules\Compliance\Domain\ExpiryDigestStatus;
use Illuminate\Database\Eloquent\Model;

final class ExpiryDigestRequest extends Model
{
    protected $table = 'compliance_expiry_digest_requests';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ExpiryDigestStatus::class,
            'queued_at' => 'immutable_datetime',
            'processing_started_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
