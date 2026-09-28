<?php

namespace App\Modules\Recruitment\Interfaces\Http\Resources;

use App\Modules\Recruitment\Application\Data\JobQualificationRequirementRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JobQualificationRequirementRecord */
final class JobQualificationRequirementResource extends JsonResource
{
    /** @return array<string, int|string> */
    public function toArray(Request $request): array
    {
        /** @var JobQualificationRequirementRecord $requirement */
        $requirement = $this->resource;

        return [
            'id' => $requirement->id,
            'qualification_definition_id' => $requirement->qualificationDefinitionId,
            'qualification_name' => $requirement->qualificationName,
            'created_at' => $requirement->createdAt->format(DATE_ATOM),
        ];
    }
}
