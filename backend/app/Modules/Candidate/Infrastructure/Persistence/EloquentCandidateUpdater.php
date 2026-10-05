<?php

namespace App\Modules\Candidate\Infrastructure\Persistence;

use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Application\Data\AuditEvent;
use App\Modules\Candidate\Application\Contracts\CandidateUpdater;
use App\Modules\Candidate\Application\Data\CandidateRecord;
use App\Modules\Candidate\Application\Exceptions\StaleCandidate;
use App\Modules\Candidate\Application\Support\CandidateVersionFingerprint;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final readonly class EloquentCandidateUpdater implements CandidateUpdater
{
    public function __construct(private AuditRecorder $audit) {}

    public function update(
        int $organisationId,
        int $actorUserId,
        int $candidateId,
        array $changes,
        ?DateTimeImmutable $expectedUpdatedAt = null,
        ?string $expectedFingerprint = null,
    ): ?CandidateRecord {
        return DB::transaction(function () use ($organisationId, $actorUserId, $candidateId, $changes, $expectedUpdatedAt, $expectedFingerprint): ?CandidateRecord {
            $candidate = Candidate::query()->where('organisation_id', $organisationId)->whereKey($candidateId)->lockForUpdate()->first();

            if ($candidate === null) {
                return null;
            }

            $updatedAt = $candidate->getAttribute('updated_at');
            if ($expectedUpdatedAt !== null && (! $updatedAt instanceof DateTimeInterface
                || $updatedAt->format('U.u') !== $expectedUpdatedAt->format('U.u'))) {
                throw new StaleCandidate;
            }
            if ($expectedFingerprint !== null && ! hash_equals($expectedFingerprint, CandidateVersionFingerprint::for(EloquentCandidateMapper::toRecord($candidate)))) {
                throw new StaleCandidate;
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
