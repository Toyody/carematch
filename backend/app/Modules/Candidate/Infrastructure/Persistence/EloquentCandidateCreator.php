<?php

namespace App\Modules\Candidate\Infrastructure\Persistence;

use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Application\Data\AuditEvent;
use App\Modules\Candidate\Application\Contracts\CandidateCreator;
use App\Modules\Candidate\Application\Data\CandidateData;
use App\Modules\Candidate\Application\Data\CandidateRecord;
use Illuminate\Support\Facades\DB;

final readonly class EloquentCandidateCreator implements CandidateCreator
{
    public function __construct(private AuditRecorder $audit) {}

    public function create(int $organisationId, int $actorUserId, CandidateData $data): CandidateRecord
    {
        return DB::transaction(function () use ($organisationId, $actorUserId, $data): CandidateRecord {
            $candidate = Candidate::query()->create([
                'organisation_id' => $organisationId, 'first_name' => $data->firstName, 'last_name' => $data->lastName,
                'email' => $data->email, 'phone' => $data->phone, 'occupation' => $data->occupation,
                'location' => $data->location, 'availability' => $data->availability, 'notes' => $data->notes,
            ]);

            $this->audit->record(new AuditEvent($organisationId, $actorUserId, 'candidate.created', 'candidate', (int) $candidate->getKey()));

            return EloquentCandidateMapper::toRecord($candidate);
        });
    }
}
