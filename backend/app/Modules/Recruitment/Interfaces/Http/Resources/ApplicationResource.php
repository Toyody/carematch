<?php

namespace App\Modules\Recruitment\Interfaces\Http\Resources;

use App\Modules\Recruitment\Application\Data\ApplicationSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApplicationSummary */
final class ApplicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ApplicationSummary $application */
        $application = $this->resource;

        return [
            'id' => $application->id,
            'job' => ['id' => $application->jobId, 'title' => $application->jobTitle],
            'candidate' => [
                'id' => $application->candidateId,
                'first_name' => $application->candidateFirstName,
                'last_name' => $application->candidateLastName,
            ],
            'status' => $application->status->value,
            'applied_at' => $application->appliedAt->format(DATE_ATOM),
            'created_by_user_id' => $application->createdByUserId,
            'created_at' => $application->createdAt->format(DATE_ATOM),
            'updated_at' => $application->updatedAt->format(DATE_ATOM),
        ];
    }
}
