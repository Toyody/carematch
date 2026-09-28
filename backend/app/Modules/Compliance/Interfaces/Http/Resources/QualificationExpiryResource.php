<?php

namespace App\Modules\Compliance\Interfaces\Http\Resources;

use App\Modules\Compliance\Application\Data\QualificationExpiryRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QualificationExpiryRecord */
final class QualificationExpiryResource extends JsonResource
{
    /** @return array<string, int|string> */
    public function toArray(Request $request): array
    {
        /** @var QualificationExpiryRecord $record */
        $record = $this->resource;

        return [
            'candidate_qualification_id' => $record->candidateQualificationId,
            'candidate_id' => $record->candidateId,
            'candidate_name' => $record->candidateName,
            'qualification_definition_id' => $record->qualificationDefinitionId,
            'qualification_name' => $record->qualificationName,
            'expires_on' => $record->expiresOn->format('Y-m-d'),
            'status' => $record->status->value,
        ];
    }
}
