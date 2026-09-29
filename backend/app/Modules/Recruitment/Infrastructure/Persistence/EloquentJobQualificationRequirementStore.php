<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Application\Data\AuditEvent;
use App\Modules\Compliance\Application\Contracts\QualificationDefinitionReferences;
use App\Modules\Recruitment\Application\Contracts\JobQualificationRequirementStore;
use App\Modules\Recruitment\Application\Data\JobQualificationRequirementRecord;
use App\Modules\Recruitment\Application\Exceptions\DuplicateJobQualificationRequirement;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class EloquentJobQualificationRequirementStore implements JobQualificationRequirementStore
{
    public function __construct(private QualificationDefinitionReferences $definitions, private AuditRecorder $audit) {}

    public function all(int $organisationId, int $jobId): ?array
    {
        if (! Job::query()->where('organisation_id', $organisationId)->whereKey($jobId)->exists()) {
            return null;
        }

        $requirements = JobQualificationRequirement::query()->where('organisation_id', $organisationId)
            ->where('job_id', $jobId)->orderBy('id')->get();
        $definitionIds = array_values($requirements->pluck('qualification_definition_id')
            ->map(static fn (mixed $id): int => (int) $id)->unique()->all());
        $names = $this->definitions->names($organisationId, $definitionIds);

        return array_values($requirements->map(fn (JobQualificationRequirement $requirement): JobQualificationRequirementRecord => $this->record(
            $requirement,
            $names[(int) $requirement->getAttribute('qualification_definition_id')] ?? '',
        ))->sortBy(static fn (JobQualificationRequirementRecord $record): string => $record->qualificationName)->values()->all());
    }

    public function add(int $organisationId, int $actorUserId, int $jobId, int $definitionId): ?JobQualificationRequirementRecord
    {
        try {
            return DB::transaction(function () use ($organisationId, $actorUserId, $jobId, $definitionId): ?JobQualificationRequirementRecord {
                $jobExists = Job::query()->where('organisation_id', $organisationId)->whereKey($jobId)->exists();
                $definitionExists = $this->definitions->activeExists($organisationId, $definitionId);
                if (! $jobExists || ! $definitionExists) {
                    return null;
                }
                if (JobQualificationRequirement::query()->where('organisation_id', $organisationId)
                    ->where('job_id', $jobId)->where('qualification_definition_id', $definitionId)->exists()) {
                    throw new DuplicateJobQualificationRequirement;
                }
                $requirement = JobQualificationRequirement::query()->create([
                    'organisation_id' => $organisationId, 'job_id' => $jobId, 'qualification_definition_id' => $definitionId,
                ]);
                $this->audit->record(new AuditEvent($organisationId, $actorUserId, 'job_qualification_requirement.added', 'job_qualification_requirement', (int) $requirement->getKey(), [
                    'job_id' => $jobId, 'qualification_definition_id' => $definitionId,
                ]));

                return $this->record($requirement);
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), 'job_qualification_requirements_unique')) {
                throw new DuplicateJobQualificationRequirement;
            }
            throw $exception;
        }
    }

    public function delete(int $organisationId, int $actorUserId, int $jobId, int $requirementId): bool
    {
        return DB::transaction(function () use ($organisationId, $actorUserId, $jobId, $requirementId): bool {
            $requirement = JobQualificationRequirement::query()->where('organisation_id', $organisationId)
                ->where('job_id', $jobId)->whereKey($requirementId)->lockForUpdate()->first();
            if ($requirement === null) {
                return false;
            }
            $definitionId = (int) $requirement->getAttribute('qualification_definition_id');
            $requirement->delete();
            $this->audit->record(new AuditEvent($organisationId, $actorUserId, 'job_qualification_requirement.removed', 'job_qualification_requirement', $requirementId, [
                'job_id' => $jobId, 'qualification_definition_id' => $definitionId,
            ]));

            return true;
        });
    }

    private function record(JobQualificationRequirement $requirement, ?string $definitionName = null): JobQualificationRequirementRecord
    {
        $definitionId = (int) $requirement->getAttribute('qualification_definition_id');
        $definitionName ??= $this->definitions->names(
            (int) $requirement->getAttribute('organisation_id'),
            [$definitionId],
        )[$definitionId] ?? '';

        return new JobQualificationRequirementRecord(
            (int) $requirement->getKey(), $definitionId, $definitionName,
            CarbonImmutable::instance($requirement->getAttribute('created_at')),
        );
    }
}
