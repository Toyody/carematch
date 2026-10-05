<?php

namespace App\Modules\Candidate\Application\Support;

use App\Modules\Candidate\Application\Data\CandidateRecord;

final class CandidateVersionFingerprint
{
    public static function for(CandidateRecord $candidate): string
    {
        return hash('sha256', (string) json_encode([
            'first_name' => $candidate->firstName, 'last_name' => $candidate->lastName,
            'email' => $candidate->email, 'phone' => $candidate->phone,
            'occupation' => $candidate->occupation, 'location' => $candidate->location,
            'latitude' => $candidate->latitude, 'longitude' => $candidate->longitude,
            'availability' => $candidate->availability, 'notes' => $candidate->notes,
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
