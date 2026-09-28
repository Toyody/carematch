<?php

namespace App\Modules\Compliance\Interfaces\Http\Resources;

use App\Modules\Compliance\Domain\QualificationCoverage;
use App\Modules\Compliance\Domain\QualificationRequirementResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QualificationCoverage */
final class QualificationCoverageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var QualificationCoverage $coverage */
        $coverage = $this->resource;

        return [
            'status' => $coverage->status->value,
            'requirements' => array_map(static fn (QualificationRequirementResult $result): array => [
                'qualification_definition_id' => $result->qualificationDefinitionId,
                'name' => $result->name,
                'status' => $result->status->value,
                'candidate_qualification_id' => $result->candidateQualificationId,
                'expires_on' => $result->expiresOn?->format('Y-m-d'),
            ], $coverage->requirements),
        ];
    }
}
