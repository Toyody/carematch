<?php

namespace App\Modules\Candidate\Infrastructure\Persistence;

use App\Modules\Candidate\Application\Contracts\CandidateCreator;
use App\Modules\Candidate\Application\Data\CandidateData;
use App\Modules\Candidate\Application\Data\CandidateRecord;

final class EloquentCandidateCreator implements CandidateCreator
{
    public function create(int $organisationId, CandidateData $data): CandidateRecord
    {
        $candidate = Candidate::query()->create([
            'organisation_id' => $organisationId,
            'first_name' => $data->firstName,
            'last_name' => $data->lastName,
            'email' => $data->email,
            'phone' => $data->phone,
            'occupation' => $data->occupation,
            'location' => $data->location,
            'availability' => $data->availability,
            'notes' => $data->notes,
        ]);

        return EloquentCandidateMapper::toRecord($candidate);
    }
}
