<?php

namespace App\Modules\Candidate\Infrastructure\Persistence;

use App\Modules\Candidate\Application\Contracts\CandidateQualificationStore;
use App\Modules\Candidate\Application\Data\CandidateQualificationData;
use App\Modules\Candidate\Application\Data\CandidateQualificationRecord;
use App\Modules\Compliance\Application\Contracts\QualificationDefinitionReferences;
use Carbon\CarbonImmutable;

final readonly class EloquentCandidateQualificationStore implements CandidateQualificationStore
{
    public function __construct(private QualificationDefinitionReferences $definitions) {}

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

    public function create(int $organisationId, int $candidateId, CandidateQualificationData $data): ?CandidateQualificationRecord
    {
        if (! $this->targetsExist($organisationId, $candidateId, $data->qualificationDefinitionId)) {
            return null;
        }

        return $this->record(CandidateQualification::query()->create($this->attributes($organisationId, $candidateId, $data)));
    }

    public function update(int $organisationId, int $candidateId, int $credentialId, CandidateQualificationData $data): ?CandidateQualificationRecord
    {
        $credential = CandidateQualification::query()->where('organisation_id', $organisationId)
            ->where('candidate_id', $candidateId)->whereKey($credentialId)->first();
        if ($credential === null) {
            return null;
        }
        $currentDefinitionId = (int) $credential->getAttribute('qualification_definition_id');
        if ($currentDefinitionId !== $data->qualificationDefinitionId
            && ! $this->activeDefinitionExists($organisationId, $data->qualificationDefinitionId)) {
            return null;
        }
        $credential->update($this->attributes($organisationId, $candidateId, $data));

        return $this->record($credential->fresh() ?? $credential);
    }

    public function delete(int $organisationId, int $candidateId, int $credentialId): bool
    {
        return CandidateQualification::query()->where('organisation_id', $organisationId)
            ->where('candidate_id', $candidateId)->whereKey($credentialId)->delete() === 1;
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
