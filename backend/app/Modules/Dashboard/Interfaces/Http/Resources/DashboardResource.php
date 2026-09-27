<?php

namespace App\Modules\Dashboard\Interfaces\Http\Resources;

use App\Modules\Dashboard\Application\Data\DashboardActivity;
use App\Modules\Dashboard\Application\Data\DashboardSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DashboardSnapshot */
final class DashboardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var DashboardSnapshot $dashboard */
        $dashboard = $this->resource;

        return [
            'candidate_count' => $dashboard->candidateCount,
            'open_job_count' => $dashboard->openJobCount,
            'application_counts' => $dashboard->applicationCounts,
            'recent_application_activity' => array_map(
                static fn (DashboardActivity $activity): array => [
                    'application_id' => $activity->applicationId,
                    'candidate' => [
                        'id' => $activity->candidateId,
                        'first_name' => $activity->candidateFirstName,
                        'last_name' => $activity->candidateLastName,
                    ],
                    'job' => [
                        'id' => $activity->jobId,
                        'title' => $activity->jobTitle,
                    ],
                    'from_status' => $activity->fromStatus,
                    'to_status' => $activity->toStatus,
                    'changed_at' => $activity->changedAt->format(DATE_ATOM),
                ],
                $dashboard->recentApplicationActivity,
            ),
        ];
    }
}
