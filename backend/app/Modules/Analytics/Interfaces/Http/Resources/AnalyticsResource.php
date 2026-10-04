<?php

namespace App\Modules\Analytics\Interfaces\Http\Resources;

use App\Modules\Analytics\Application\Data\AnalyticsSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AnalyticsSnapshot */
final class AnalyticsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AnalyticsSnapshot $analytics */
        $analytics = $this->resource;

        return [
            'period' => [
                'from' => $analytics->period->from->format('Y-m-d'),
                'to' => $analytics->period->to->format('Y-m-d'),
                'timezone' => 'UTC',
            ],
            'summary' => $analytics->summary,
            'funnel' => $analytics->funnel,
            'current_statuses' => $analytics->currentStatuses,
            'application_series' => $analytics->applicationSeries,
            'time_to_stage' => $analytics->timeToStage,
            'jobs' => $analytics->jobs,
        ];
    }
}
