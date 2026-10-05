<?php

namespace App\Modules\Ai\Interfaces\Http\Resources;

use App\Modules\Ai\Application\Data\MatchExplanationRecord;
use App\Modules\Ai\Application\Data\MatchExplanationView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MatchExplanationRecord|MatchExplanationView */
final class MatchExplanationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $view = $this->resource;
        $record = $view instanceof MatchExplanationView ? $view->explanation : $view;

        return [
            'id' => $record->id, 'job_id' => $record->jobId, 'candidate_id' => $record->candidateId,
            'status' => $record->status->value,
            'stale' => $view instanceof MatchExplanationView ? $view->stale : false,
            'summary' => $record->summary, 'factors' => $record->factors,
            'failure_code' => $record->failureCode, 'created_at' => $record->createdAt->format(DATE_ATOM),
            'disclaimer' => 'AI-generated explanation based on deterministic match factors. It does not affect ranking.',
        ];
    }
}
