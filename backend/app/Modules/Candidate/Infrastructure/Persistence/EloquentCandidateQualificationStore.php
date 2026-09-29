<?php

namespace App\Modules\Candidate\Infrastructure\Persistence;

use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Application\Data\AuditEvent;
use App\Modules\Candidate\Application\Contracts\CandidateQualificationStore;
use App\Modules\Candidate\Application\Data\CandidateQualificationData;
use App\Modules\Candidate\Application\Data\CandidateQualificationRecord;
use App\Modules\Compliance\Application\Contracts\QualificationDefinitionReferences;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class EloquentCandidateQualificationStore implements CandidateQualificationStore
{
    public function __construct(private QualificationDefinitionReferences $definitions, private AuditRecorder $audit) {}

    public function all(int $organisationId, int $candidateId): ?array
    {
        if (! Candidate::query()->where('organisation_id', $organisationId)->whereKey($candidateId)->exists()) {
            return null;
        }

        $credentials = CandidateQualification::query()->where('organisation_id', $organisationId)
            ->where('candidate_id', $candidateId)->orderByDesc('expires_on')->orderBy('id')->get();
        $names = $this->definitions->names($organisationId, array_values($credentials->pluck('qualification_definition_id')->map(static fn (mixed $id): int => (int) $id)->unique()->all()));

        return array_values($credentials
            ->map(fn (CandidateQualification $credential): CandidateQualificationRecord => $this->record(
                $credential,
                $names[(int) $credential->getAttribute('qualification_definition_id')] ?? '',
            ))->sortBy(static fn (CandidateQualificationRecord $record): string => $record->qualificationName)->values()->all());
    }

    public function create(int $organisationId, int $actorUserId, int $candidateId, CandidateQualificationData $data): ?CandidateQualificationRecord
    {
        return DB::transaction(function () use ($organisationId, $actorUserId, $candidateId, $data): ?CandidateQualificationRecord {
            if (! $this->targetsExist($organisationId, $candidateId, $data->qualificationDefinitionId)) {
                return null;
            }

            $credential = CandidateQualification::query()->create($this->attributes($organisationId, $candidateId, $data));
            $this->audit->record(new AuditEvent($organisationId, $actorUserId, 'candidate_qualification.created', 'candidate_qualification', (int) $credential->getKey(), [
                'candidate_id' => $candidateId, 'qualification_definition_id' => $data->qualificationDefinitionId,
            ]));

            return $this->record($credential);
        });
    }

    public function update(int $organisationId, int $actorUserId, int $candidateId, int $credentialId, CandidateQualificationData $data): ?CandidateQualificationRecord
    {
        return DB::transaction(function () use ($organisationId, $actorUserId, $candidateId, $credentialId, $data): ?CandidateQualificationRecord {
            $credential = CandidateQualification::query()->where('organisation_id', $organisationId)
                ->where('candidate_id', $candidateId)->whereKey($credentialId)->lockForUpdate()->first();
            if ($credential === null) {
                return null;
            }
            $currentDefinitionId = (int) $credential->getAttribute('qualification_definition_id');
            if ($currentDefinitionId !== $data->qualificationDefinitionId
                && ! $this->activeDefinitionExists($organisationId, $data->qualificationDefinitionId)) {
                return null;
            }
            $credential->fill($this->attributes($organisationId, $candidateId, $data));
            $changedFields = array_values(array_diff(array_keys($credential->getDirty()), ['organisation_id', 'candidate_id']));
            if ($changedFields !== []) {
                $credential->save();
                $this->audit->record(new AuditEvent($organisationId, $actorUserId, 'candidate_qualification.updated', 'candidate_qualification', $credentialId, [
                    'candidate_id' => $candidateId, 'qualification_definition_id' => $data->qualificationDefinitionId, 'changed_fields' => $changedFields,
                ]));
            }

            return $this->record($credential->fresh() ?? $credential);
        });
    }

    public function delete(int $organisationId, int $actorUserId, int $candidateId, int $credentialId): bool
    {
        return DB::transaction(function () use ($organisationId, $actorUserId, $candidateId, $credentialId): bool {
            $credential = CandidateQualification::query()->where('organisation_id', $organisationId)
                ->where('candidate_id', $candidateId)->whereKey($credentialId)->lockForUpdate()->first();
            if ($credential === null) {
                return false;
            }
            $definitionId = (int) $credential->getAttribute('qualification_definition_id');
            $credential->delete();
            $this->audit->record(new AuditEvent($organisationId, $actorUserId, 'candidate_qualification.deleted', 'candidate_qualification', $credentialId, [
                'candidate_id' => $candidateId, 'qualification_definition_id' => $definitionId,
            ]));

            return true;
        });
    }

    private function targetsExist(int $organisationId, int $candidateId, int $definitionId): bool
    {
        return Candidate::query()->where('organisation_id', $organisationId)->whereKey($candidateId)->exists()
            && $this->activeDefinitionExists($organisationId, $definitionId);
    }

    private function activeDefinitionExists(int $organisationId, int $definitionId): bool
    {
        return $this->definitions->activeExists($organisationId, $definitionId);
    }

    /** @return array<string, int|string|null> */
    private function attributes(int $organisationId, int $candidateId, CandidateQualificationData $data): array
    {
        return [
            'organisation_id' => $organisationId, 'candidate_id' => $candidateId,
            'qualification_definition_id' => $data->qualificationDefinitionId,
            'issuer' => $data->issuer, 'credential_number' => $data->credentialNumber,
            'issued_on' => $data->issuedOn?->format('Y-m-d'), 'expires_on' => $data->expiresOn?->format('Y-m-d'),
        ];
    }

    private function record(CandidateQualification $credential, ?string $definitionName = null): CandidateQualificationRecord
    {
        $definitionName ??= $this->definitions->names(
            (int) $credential->getAttribute('organisation_id'),
            [(int) $credential->getAttribute('qualification_definition_id')],
        )[(int) $credential->getAttribute('qualification_definition_id')] ?? '';

        return new CandidateQualificationRecord(
            (int) $credential->getKey(), (int) $credential->getAttribute('qualification_definition_id'), $definitionName,
            $credential->getAttribute('issuer'), $credential->getAttribute('credential_number'),
            $credential->getAttribute('issued_on'), $credential->getAttribute('expires_on'),
            CarbonImmutable::instance($credential->getAttribute('created_at')),
            CarbonImmutable::instance($credential->getAttribute('updated_at')),
        );
    }
}
