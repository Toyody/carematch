<?php

namespace Database\Seeders;

use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Candidate\Infrastructure\Persistence\CandidateQualification;
use App\Modules\Compliance\Infrastructure\Persistence\QualificationDefinition;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use App\Modules\Recruitment\Domain\ApplicationStatus;
use App\Modules\Recruitment\Domain\JobStatus;
use App\Modules\Recruitment\Infrastructure\Persistence\ApplicationStatusHistory;
use App\Modules\Recruitment\Infrastructure\Persistence\Job;
use App\Modules\Recruitment\Infrastructure\Persistence\JobQualificationRequirement;
use App\Modules\Recruitment\Infrastructure\Persistence\RecruitmentApplication;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PortfolioDemoSeeder extends Seeder
{
    private const string DEMO_USER_NAME = 'Alex Morgan (Demo Admin)';

    private const string DEMO_ORGANISATION_NAME = 'Harbourlight Health Staffing (Demo)';

    private const string DEMO_CANDIDATE_NOTE = 'Synthetic portfolio profile. No real personal information.';

    private const string DEMO_JOB_DESCRIPTION = 'Synthetic portfolio vacancy used to demonstrate the CareMatch recruitment workflow.';

    public function run(): void
    {
        $this->assertSafeToRun();

        $this->provision();
    }

    /**
     * Provision the guarded synthetic dataset after the caller has applied its
     * environment-specific safety checks.
     */
    public function provision(): void
    {

        $email = $this->demoEmail();
        $password = $this->demoPassword();

        DB::transaction(function () use ($email, $password): void {
            $user = $this->user($email, $password);
            $organisation = $this->organisation($user);
            $candidates = $this->candidates($organisation);
            $jobs = $this->jobs($organisation);

            $this->qualificationData($organisation, $candidates, $jobs);

            $this->applications($organisation, $user, $candidates, $jobs);
        });

        $this->command?->info(sprintf(
            'Synthetic portfolio demo data is ready for %s.',
            $email,
        ));
    }

    private function assertSafeToRun(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Portfolio demo seeding is disabled in production.');
        }

        if (config('carematch.portfolio_demo.enabled') !== true) {
            throw new RuntimeException('Set CARE_MATCH_DEMO_DATA=true to seed portfolio demo data.');
        }
    }

    private function demoEmail(): string
    {
        $email = config('carematch.portfolio_demo.email');

        if (! is_string($email)) {
            throw new RuntimeException('CARE_MATCH_DEMO_EMAIL must be a string.');
        }

        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || ! str_ends_with($email, '@example.test')) {
            throw new RuntimeException('CARE_MATCH_DEMO_EMAIL must use the reserved @example.test domain.');
        }

        return $email;
    }

    private function demoPassword(): string
    {
        $password = config('carematch.portfolio_demo.password');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('CARE_MATCH_DEMO_PASSWORD is required.');
        }

        if (mb_strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new RuntimeException('CARE_MATCH_DEMO_PASSWORD must contain 12 or more characters, no NUL byte and at most 72 bytes.');
        }

        return $password;
    }

    private function user(string $email, string $password): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user !== null && $user->getAttribute('name') !== self::DEMO_USER_NAME) {
            throw new RuntimeException('The configured demo email belongs to a non-demo user.');
        }

        if ($user === null) {
            return User::query()->create([
                'name' => self::DEMO_USER_NAME,
                'email' => $email,
                'password' => $password,
            ]);
        }

        $user->update(['password' => $password]);

        return $user;
    }

    private function organisation(User $user): Organisation
    {
        $organisations = Organisation::query()
            ->where('name', self::DEMO_ORGANISATION_NAME)
            ->get();

        if ($organisations->count() > 1) {
            throw new RuntimeException('Multiple demo Organisations already exist.');
        }

        $organisation = $organisations->first();
        if ($organisation === null) {
            $organisation = Organisation::query()->create([
                'name' => self::DEMO_ORGANISATION_NAME,
            ]);
            OrganisationMembership::query()->create([
                'organisation_id' => $organisation->getKey(),
                'user_id' => $user->getKey(),
                'role' => 'admin',
                'deactivated_at' => null,
            ]);

            return $organisation;
        }

        $membership = OrganisationMembership::query()
            ->where('organisation_id', $organisation->getKey())
            ->where('user_id', $user->getKey())
            ->first();

        if ($membership === null || $membership->getAttribute('role') !== 'admin') {
            throw new RuntimeException('The existing demo Organisation is not owned by the configured demo Admin.');
        }

        $membership->update(['deactivated_at' => null]);

        return $organisation;
    }

    /** @return array<string, Candidate> */
    private function candidates(Organisation $organisation): array
    {
        $definitions = [
            'avery' => ['Avery', 'Morgan', 'Registered Nurse', 'avery.morgan@example.test', 'North District', 'Available now'],
            'maya' => ['Maya', 'Chen', 'Physiotherapist', 'maya.chen@example.test', 'West District', 'Four weeks notice'],
            'elliot' => ['Elliot', 'Brooks', 'Occupational Therapist', 'elliot.brooks@example.test', 'Central District', 'Available now'],
            'priya' => ['Priya', 'Shah', 'Clinical Support Worker', 'priya.shah@example.test', 'South District', 'Two weeks notice'],
            'jordan' => ['Jordan', 'Lee', 'Registered Nurse', 'jordan.lee@example.test', 'East District', 'Available from October'],
            'casey' => ['Casey', 'Taylor', 'Physiotherapist', 'casey.taylor@example.test', 'North District', 'Available now'],
        ];

        $candidates = [];
        foreach ($definitions as $key => [$firstName, $lastName, $occupation, $email, $location, $availability]) {
            $matches = Candidate::query()
                ->where('organisation_id', $organisation->getKey())
                ->where('email', $email)
                ->get();

            if ($matches->count() > 1) {
                throw new RuntimeException(sprintf('Multiple demo Candidates use %s.', $email));
            }

            $candidate = $matches->first();
            $attributes = [
                'organisation_id' => $organisation->getKey(),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => null,
                'occupation' => $occupation,
                'location' => $location,
                'availability' => $availability,
                'notes' => self::DEMO_CANDIDATE_NOTE,
            ];

            if ($candidate === null) {
                $candidate = Candidate::query()->create($attributes);
            } elseif (
                $candidate->getAttribute('first_name') !== $firstName
                || $candidate->getAttribute('last_name') !== $lastName
                || $candidate->getAttribute('notes') !== self::DEMO_CANDIDATE_NOTE
            ) {
                throw new RuntimeException(sprintf('Demo Candidate email %s conflicts with existing data.', $email));
            } else {
                $candidate->update($attributes);
            }

            $candidates[$key] = $candidate;
        }

        return $candidates;
    }

    /** @return array<string, Job> */
    private function jobs(Organisation $organisation): array
    {
        $definitions = [
            'nurse' => ['Registered Nurse — Acute Care', 'Registered Nurse', 'North District', 'Full-time', JobStatus::Open],
            'physio' => ['Physiotherapist — Community Rehabilitation', 'Physiotherapist', 'West District', 'Part-time', JobStatus::Open],
            'occupational' => ['Occupational Therapist — Rehabilitation Services', 'Occupational Therapist', 'Central District', 'Full-time', JobStatus::Open],
            'support' => ['Clinical Support Worker — Inpatient Services', 'Clinical Support Worker', 'South District', 'Full-time', JobStatus::Closed],
            'paediatric' => ['Occupational Therapist — Paediatric Services', 'Occupational Therapist', 'East District', 'Part-time', JobStatus::Draft],
            'outpatient' => ['Registered Nurse — Outpatient Clinic', 'Registered Nurse', 'Central District', 'Casual', JobStatus::Archived],
        ];

        $jobs = [];
        foreach ($definitions as $key => [$title, $occupation, $location, $employmentType, $status]) {
            $matches = Job::query()
                ->where('organisation_id', $organisation->getKey())
                ->where('title', $title)
                ->get();

            if ($matches->count() > 1) {
                throw new RuntimeException(sprintf('Multiple demo Jobs use the title %s.', $title));
            }

            $attributes = [
                'organisation_id' => $organisation->getKey(),
                'title' => $title,
                'occupation' => $occupation,
                'location' => $location,
                'employment_type' => $employmentType,
                'description' => self::DEMO_JOB_DESCRIPTION,
                'status' => $status,
                'opened_at' => $status === JobStatus::Draft ? null : '2026-09-01 09:00:00+00',
                'closes_at' => $status === JobStatus::Archived ? '2026-09-20 17:00:00+00' : '2026-12-15 17:00:00+00',
            ];

            $job = $matches->first();
            if ($job === null) {
                $job = Job::query()->create($attributes);
            } elseif ($job->getAttribute('description') !== self::DEMO_JOB_DESCRIPTION) {
                throw new RuntimeException(sprintf('Demo Job title %s conflicts with existing data.', $title));
            } else {
                $job->update($attributes);
            }

            $jobs[$key] = $job;
        }

        return $jobs;
    }

    /**
     * @param  array<string, Candidate>  $candidates
     * @param  array<string, Job>  $jobs
     */
    private function qualificationData(Organisation $organisation, array $candidates, array $jobs): void
    {
        $definitions = [];
        foreach ([
            'Registered Nurse registration' => 'Professional registration',
            'CPR' => 'Emergency response',
            'First Aid' => 'Emergency response',
            'Working with Children Check' => 'Screening',
        ] as $name => $category) {
            $definitions[$name] = QualificationDefinition::query()->updateOrCreate(
                ['organisation_id' => $organisation->getKey(), 'name' => $name],
                ['category' => $category, 'description' => 'Synthetic portfolio qualification definition.', 'is_active' => true],
            );
        }

        $today = CarbonImmutable::today('UTC');
        $credentials = [
            [$candidates['avery'], $definitions['Registered Nurse registration'], 'SYNTHETIC-RN-AVERY', $today->addYear()],
            [$candidates['avery'], $definitions['CPR'], 'SYNTHETIC-CPR-AVERY', $today->addDays(10)],
            [$candidates['maya'], $definitions['First Aid'], 'SYNTHETIC-FA-MAYA', $today->subDay()],
            [$candidates['elliot'], $definitions['Working with Children Check'], 'SYNTHETIC-WCC-ELLIOT', null],
        ];
        foreach ($credentials as [$candidate, $definition, $number, $expiry]) {
            CandidateQualification::query()->updateOrCreate(
                [
                    'organisation_id' => $organisation->getKey(),
                    'candidate_id' => $candidate->getKey(),
                    'credential_number' => $number,
                ],
                [
                    'qualification_definition_id' => $definition->getKey(),
                    'issuer' => 'Synthetic Training Provider',
                    'issued_on' => $today->subYear()->format('Y-m-d'),
                    'expires_on' => $expiry?->format('Y-m-d'),
                ],
            );
        }

        foreach ([$definitions['Registered Nurse registration'], $definitions['CPR'], $definitions['First Aid']] as $definition) {
            JobQualificationRequirement::query()->firstOrCreate([
                'organisation_id' => $organisation->getKey(),
                'job_id' => $jobs['nurse']->getKey(),
                'qualification_definition_id' => $definition->getKey(),
            ]);
        }
    }

    /**
     * @param  array<string, Candidate>  $candidates
     * @param  array<string, Job>  $jobs
     */
    private function applications(Organisation $organisation, User $user, array $candidates, array $jobs): void
    {
        $definitions = [
            [$candidates['avery'], $jobs['nurse'], ApplicationStatus::Hired, '2026-09-27 15:00:00+00'],
            [$candidates['maya'], $jobs['physio'], ApplicationStatus::Offer, '2026-09-27 13:00:00+00'],
            [$candidates['elliot'], $jobs['occupational'], ApplicationStatus::Interview, '2026-09-27 11:30:00+00'],
            [$candidates['priya'], $jobs['support'], ApplicationStatus::Rejected, '2026-09-26 16:00:00+00'],
            [$candidates['jordan'], $jobs['nurse'], ApplicationStatus::Screening, '2026-09-27 10:15:00+00'],
            [$candidates['casey'], $jobs['physio'], ApplicationStatus::Applied, '2026-09-27 09:00:00+00'],
        ];

        foreach ($definitions as [$candidate, $job, $status, $completedAt]) {
            $this->application($organisation, $user, $candidate, $job, $status, $completedAt);
        }
    }

    private function application(
        Organisation $organisation,
        User $user,
        Candidate $candidate,
        Job $job,
        ApplicationStatus $status,
        string $completedAt,
    ): void {
        $application = RecruitmentApplication::query()
            ->where('organisation_id', $organisation->getKey())
            ->where('job_id', $job->getKey())
            ->where('candidate_id', $candidate->getKey())
            ->first();
        $history = $this->historyFor($status, CarbonImmutable::parse($completedAt));

        if ($application !== null) {
            $this->assertApplicationMatches($application, $user, $status, $history);

            return;
        }

        $application = RecruitmentApplication::query()->create([
            'organisation_id' => $organisation->getKey(),
            'job_id' => $job->getKey(),
            'candidate_id' => $candidate->getKey(),
            'status' => $status,
            'applied_at' => $history[0]['created_at'],
            'created_by_user_id' => $user->getKey(),
        ]);

        foreach ($history as $event) {
            $record = new ApplicationStatusHistory;
            $record->forceFill([
                'organisation_id' => $organisation->getKey(),
                'application_id' => $application->getKey(),
                'from_status' => $event['from_status'],
                'to_status' => $event['to_status'],
                'changed_by_user_id' => $user->getKey(),
                'note' => $event['note'],
                'created_at' => $event['created_at'],
            ])->save();
        }
    }

    /**
     * @return list<array{from_status: ApplicationStatus|null, to_status: ApplicationStatus, note: string|null, created_at: CarbonImmutable}>
     */
    private function historyFor(ApplicationStatus $finalStatus, CarbonImmutable $completedAt): array
    {
        $path = match ($finalStatus) {
            ApplicationStatus::Applied => [ApplicationStatus::Applied],
            ApplicationStatus::Screening => [ApplicationStatus::Applied, ApplicationStatus::Screening],
            ApplicationStatus::Interview => [ApplicationStatus::Applied, ApplicationStatus::Screening, ApplicationStatus::Interview],
            ApplicationStatus::Offer => [ApplicationStatus::Applied, ApplicationStatus::Screening, ApplicationStatus::Interview, ApplicationStatus::Offer],
            ApplicationStatus::Hired => [ApplicationStatus::Applied, ApplicationStatus::Screening, ApplicationStatus::Interview, ApplicationStatus::Offer, ApplicationStatus::Hired],
            ApplicationStatus::Rejected => [ApplicationStatus::Applied, ApplicationStatus::Screening, ApplicationStatus::Rejected],
        };
        $firstAt = $completedAt->subHours(count($path) - 1);
        $history = [];

        foreach ($path as $index => $toStatus) {
            $history[] = [
                'from_status' => $index === 0 ? null : $path[$index - 1],
                'to_status' => $toStatus,
                'note' => $index === count($path) - 1 && count($path) > 1
                    ? 'Synthetic portfolio workflow update.'
                    : null,
                'created_at' => $firstAt->addHours($index),
            ];
        }

        return $history;
    }

    /**
     * @param  list<array{from_status: ApplicationStatus|null, to_status: ApplicationStatus, note: string|null, created_at: CarbonImmutable}>  $expectedHistory
     */
    private function assertApplicationMatches(
        RecruitmentApplication $application,
        User $user,
        ApplicationStatus $status,
        array $expectedHistory,
    ): void {
        if (
            $application->getAttribute('status') !== $status
            || $application->getAttribute('created_by_user_id') !== $user->getKey()
        ) {
            throw new RuntimeException('Existing demo Application conflicts with the expected synthetic state.');
        }

        $actualHistory = ApplicationStatusHistory::query()
            ->where('organisation_id', $application->getAttribute('organisation_id'))
            ->where('application_id', $application->getKey())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($actualHistory->count() !== count($expectedHistory)) {
            throw new RuntimeException('Existing demo Application history conflicts with the expected synthetic state.');
        }

        foreach ($actualHistory as $index => $event) {
            if (
                $event->getAttribute('from_status') !== $expectedHistory[$index]['from_status']
                || $event->getAttribute('to_status') !== $expectedHistory[$index]['to_status']
            ) {
                throw new RuntimeException('Existing demo Application history contains an invalid transition.');
            }
        }
    }
}
