<?php

namespace App\Modules\Ai\Infrastructure\Persistence;

use App\Modules\Ai\Domain\AiOperationStatus;
use Illuminate\Database\Eloquent\Model;

final class AiMatchExplanation extends Model
{
    protected $table = 'ai_match_explanations';

    /** @var list<string> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => AiOperationStatus::class,
            'factors' => 'array',
            'processing_started_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
