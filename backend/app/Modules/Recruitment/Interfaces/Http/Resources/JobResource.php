<?php

namespace App\Modules\Recruitment\Interfaces\Http\Resources;

use App\Modules\Recruitment\Application\Data\JobRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JobRecord */
final class JobResource extends JsonResource
{
    /** @return array<string, int|string|null> */
    public function toArray(Request $request): array
    {
        /** @var JobRecord $job */
        $job = $this->resource;

        return [
            'id' => $job->id,
            'title' => $job->title,
            'occupation' => $job->occupation,
            'location' => $job->location,
            'employment_type' => $job->employmentType,
            'description' => $job->description,
            'status' => $job->status->value,
            'opened_at' => $job->openedAt?->format(DATE_ATOM),
            'closes_at' => $job->closesAt?->format(DATE_ATOM),
            'created_at' => $job->createdAt->format(DATE_ATOM),
            'updated_at' => $job->updatedAt->format(DATE_ATOM),
        ];
    }
}
