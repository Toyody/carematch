<?php

namespace App\Modules\Candidate\Infrastructure\Persistence;

use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Application\Data\AuditEvent;
use App\Modules\Candidate\Application\Contracts\CandidateUpdater;
use App\Modules\Candidate\Application\Data\CandidateRecord;
use Illuminate\Support\Facades\DB;

final readonly class EloquentCandidateUpdater implements CandidateUpdater
{
    public function __construct(private AuditRecorder $audit) {}

    public function update(
        int $organisationId,
        int $actorUserId,
        int $candidateId,
        array $changes,
    ): ?CandidateRecord {
        return DB::transaction(function () use ($organisationId, $actorUserId, $candidateId, $changes): ?CandidateRecord {
            $candidate = Candidate::query()->where('organisation_id', $organisationId)->whereKey($candidateId)->lockForUpdate()->first();

            if ($candidate === null) {
                return null;
            }

            $candidate->fill($changes);
            $changedFields = array_keys($candidate->getDirty());
            if ($changedFields !== []) {
                $candidate->save();
                $this->audit->record(new AuditEvent($organisationId, $actorUserId, 'candidate.updated', 'candidate', $candidateId, ['changed_fields' => $changedFields]));
            }

            return EloquentCandidateMapper::toRecord($candidate);
        });
    }
}
