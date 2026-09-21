<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Candidate\Application\Contracts\CandidateReferenceLookup;
use App\Modules\Recruitment\Application\Contracts\ApplicationCreator;
use App\Modules\Recruitment\Application\Data\ApplicationSummary;
use App\Modules\Recruitment\Application\Exceptions\ApplicationTargetNotFound;
use App\Modules\Recruitment\Application\Exceptions\DuplicateApplication;
use App\Modules\Recruitment\Application\Exceptions\JobNotOpenForApplication;
use App\Modules\Recruitment\Domain\ApplicationStatus;
use App\Modules\Recruitment\Domain\JobStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class EloquentApplicationCreator implements ApplicationCreator
{
    private const string DUPLICATE_CONSTRAINT = 'applications_org_job_candidate_unique';

    public function __construct(private CandidateReferenceLookup $candidates) {}

    public function create(int $organisationId, int $actorUserId, int $jobId, int $candidateId): ApplicationSummary
    {
        try {
            return DB::transaction(function () use ($organisationId, $actorUserId, $jobId, $candidateId): ApplicationSummary {
                $job = Job::query()
                    ->where('organisation_id', $organisationId)
                    ->whereKey($jobId)
                    ->lockForUpdate()
                    ->first(['id', 'title', 'status']);

                if ($job === null) {
                    throw new ApplicationTargetNotFound;
                }
                if ($job->getAttribute('status') !== JobStatus::Open) {
                    throw new JobNotOpenForApplication;
                }

                $candidate = $this->candidates->find($organisationId, $candidateId);
                if ($candidate === null) {
                    throw new ApplicationTargetNotFound;
                }

                $appliedAt = CarbonImmutable::now('UTC');
                $application = RecruitmentApplication::query()->create([
                    'organisation_id' => $organisationId,
                    'job_id' => $jobId,
                    'candidate_id' => $candidateId,
                    'status' => ApplicationStatus::Applied,
                    'applied_at' => $appliedAt,
                    'created_by_user_id' => $actorUserId,
                ]);

                ApplicationStatusHistory::query()->create([
                    'organisation_id' => $organisationId,
                    'application_id' => $application->getKey(),
                    'from_status' => null,
                    'to_status' => ApplicationStatus::Applied,
                    'changed_by_user_id' => $actorUserId,
                    'note' => null,
                ]);

                $record = EloquentApplicationMapper::toRecord($application, (string) $job->getAttribute('title'));

                return new ApplicationSummary(
                    id: $record->id,
                    jobId: $record->jobId,
                    jobTitle: $record->jobTitle,
                    candidateId: $candidate->id,
                    candidateFirstName: $candidate->firstName,
                    candidateLastName: $candidate->lastName,
                    status: $record->status,
                    appliedAt: $record->appliedAt,
                    createdByUserId: $record->createdByUserId,
                    createdAt: $record->createdAt,
                    updatedAt: $record->updatedAt,
                );
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($exception->index === self::DUPLICATE_CONSTRAINT) {
                throw new DuplicateApplication($exception);
            }

            throw $exception;
        }
    }
}
