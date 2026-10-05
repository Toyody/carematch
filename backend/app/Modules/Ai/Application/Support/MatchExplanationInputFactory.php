<?php

namespace App\Modules\Ai\Application\Support;

use App\Modules\Ai\Application\Data\MatchExplanationInput;
use App\Modules\Matching\Application\Data\MatchExplanationSourceData;

final class MatchExplanationInputFactory
{
    public static function fromSource(MatchExplanationSourceData $source): MatchExplanationInput
    {
        return new MatchExplanationInput(
            $source->jobTitle, $source->jobOccupation, $source->candidateOccupation,
            $source->qualificationStatus, $source->qualificationRequiredCount,
            $source->qualificationSatisfiedCount, $source->qualificationAttentionCount,
            $source->occupationStatus, $source->distanceKm, $source->applicationStatus,
        );
    }
}
