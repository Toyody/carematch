<?php

namespace App\Modules\Compliance\Infrastructure\Persistence;

use App\Modules\Compliance\Application\Contracts\QualificationCoverageReadModel;
use App\Modules\Compliance\Domain\CredentialEvidence;
use App\Modules\Compliance\Domain\QualificationRequirement;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class PostgreSqlQualificationCoverageReadModel implements QualificationCoverageReadModel
{
    public function forCandidateAndJob(int $organisationId, int $jobId, int $candidateId): ?array
    {
        $jobExists = DB::table('jobs')->where('organisation_id', $organisationId)->where('id', $jobId)->exists();
        $candidateExists = DB::table('candidates')->where('organisation_id', $organisationId)->where('id', $candidateId)->exists();
        if (! $jobExists || ! $candidateExists) {
            return null;
        }

        $requirements = array_values(DB::table('job_qualification_requirements as requirements')
            ->join('qualification_definitions as definitions', function ($join): void {
                $join->on('definitions.organisation_id', '=', 'requirements.organisation_id')
                    ->on('definitions.id', '=', 'requirements.qualification_definition_id');
            })
            ->where('requirements.organisation_id', $organisationId)
            ->where('requirements.job_id', $jobId)
            ->orderBy('definitions.name')->orderBy('definitions.id')
            ->get(['definitions.id', 'definitions.name'])
            ->map(static fn (object $row): QualificationRequirement => new QualificationRequirement((int) $row->id, (string) $row->name))
            ->all());

        $definitionIds = array_map(static fn (QualificationRequirement $requirement): int => $requirement->qualificationDefinitionId, $requirements);
        $credentials = $definitionIds === [] ? [] : array_values(DB::table('candidate_qualifications')
            ->where('organisation_id', $organisationId)
            ->where('candidate_id', $candidateId)
            ->whereIn('qualification_definition_id', $definitionIds)
            ->get(['id', 'qualification_definition_id', 'expires_on'])
            ->map(static fn (object $row): CredentialEvidence => new CredentialEvidence(
                (int) $row->id,
                (int) $row->qualification_definition_id,
                $row->expires_on === null ? null : new DateTimeImmutable((string) $row->expires_on),
            ))->all());

        return ['requirements' => $requirements, 'credentials' => $credentials];
    }
}
