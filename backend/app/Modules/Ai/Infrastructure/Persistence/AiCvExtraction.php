<?php

namespace App\Modules\Ai\Infrastructure\Persistence;

use App\Modules\Ai\Domain\AiOperationStatus;
use Illuminate\Database\Eloquent\Model;

final class AiCvExtraction extends Model
{
    protected $table = 'ai_cv_extractions';

    /** @var list<string> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => AiOperationStatus::class,
            'draft' => 'array',
            'candidate_version' => 'immutable_datetime',
            'processing_started_at' => 'immutable_datetime',
            'review_ready_at' => 'immutable_datetime',
            'applied_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
