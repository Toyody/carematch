<?php

namespace App\Modules\Matching\Interfaces\Http\Resources;

use App\Modules\Matching\Application\Data\CandidateMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateMatch */
final class CandidateMatchResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CandidateMatch $match */
        $match = $this->resource;
        $notSatisfied = $match->qualificationRequirementCount
            - $match->qualificationSatisfiedCount
            - $match->qualificationAttentionCount;

        return [
            'rank' => $match->rank,
            'candidate' => [
                'id' => $match->candidateId,
                'first_name' => $match->firstName,
                'last_name' => $match->lastName,
                'occupation' => $match->occupation,
                'location' => $match->location,
            ],
            'qualification' => [
                'status' => $match->qualificationStatus->value,
                'required_count' => $match->qualificationRequirementCount,
                'satisfied_count' => $match->qualificationSatisfiedCount,
                'attention_required_count' => $match->qualificationAttentionCount,
                'not_satisfied_count' => $notSatisfied,
            ],
            'occupation_status' => $match->occupationStatus->value,
            'distance_km' => $match->distanceKm === null ? null : round($match->distanceKm, 1),
            'application_status' => $match->applicationStatus,
            'factors' => [
                ['type' => 'qualification', 'status' => $match->qualificationStatus->value],
                ['type' => 'occupation', 'status' => $match->occupationStatus->value],
                ['type' => 'distance', 'status' => $match->distanceKm === null ? 'unavailable' : 'available'],
            ],
        ];
    }
}
