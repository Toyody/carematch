<?php

namespace App\Modules\Candidate\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

final class CandidateQualification extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'organisation_id', 'candidate_id', 'qualification_definition_id', 'issuer',
        'credential_number', 'issued_on', 'expires_on',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['issued_on' => 'immutable_date', 'expires_on' => 'immutable_date'];
    }
}
