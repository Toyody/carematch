<?php

namespace Database\Seeders;

use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Candidate\Infrastructure\Persistence\CandidateDocument;
use App\Modules\Compliance\Infrastructure\Persistence\QualificationDefinition;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Recruitment\Infrastructure\Persistence\Job;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PredeploymentRehearsalSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('predeployment')) {
            throw new RuntimeException('PredeploymentRehearsalSeeder may run only in the disposable predeployment environment.');
        }

        if (config('carematch.portfolio_demo.enabled') !== true) {
            throw new RuntimeException('Synthetic rehearsal data must be explicitly enabled.');
        }

        $this->call(PortfolioDemoSeeder::class);

        DB::transaction(function (): void {
            $user = User::query()->where('email', 'rehearsal.admin@example.test')->sole();
            $organisation = Organisation::query()->where('name', 'Harbourlight Health Staffing (Demo)')->sole();
            $candidate = Candidate::query()
                ->where('organisation_id', $organisation->getKey())
                ->orderBy('id')
                ->firstOrFail();
            $job = Job::query()
                ->where('organisation_id', $organisation->getKey())
                ->orderBy('id')
                ->firstOrFail();

            $document = CandidateDocument::query()->firstOrCreate(
                ['storage_key' => hash('sha256', 'predeployment-synthetic-document')],
                [
                    'organisation_id' => $organisation->getKey(),
                    'candidate_id' => $candidate->getKey(),
                    'original_name' => 'synthetic-rehearsal.pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 64,
                    'uploaded_by_user_id' => $user->getKey(),
                ],
            );

            DB::table('compliance_expiry_digest_requests')->insertOrIgnore([
                'organisation_id' => $organisation->getKey(),
                'requested_by_user_id' => $user->getKey(),
                'idempotency_key_hash' => hash('sha256', 'predeployment-expiry-digest'),
                'request_fingerprint' => hash('sha256', 'predeployment-expiry-digest-fingerprint'),
                'status' => 'queued',
                'queued_at' => '2026-10-01 00:00:00+00',
                'created_at' => '2026-10-01 00:00:00+00',
                'updated_at' => '2026-10-01 00:00:00+00',
            ]);

            DB::table('ai_cv_extractions')->insertOrIgnore([
                'organisation_id' => $organisation->getKey(),
                'candidate_id' => $candidate->getKey(),
                'candidate_document_id' => $document->getKey(),
                'requested_by_user_id' => $user->getKey(),
                'idempotency_key_hash' => hash('sha256', 'predeployment-ai-extraction'),
                'request_fingerprint' => hash('sha256', 'predeployment-ai-extraction-fingerprint'),
                'status' => 'queued',
                'provider' => 'fake',
                'model' => 'deterministic-fake-v1',
                'prompt_version' => 'cv_extraction_prompt_v1',
                'schema_version' => 'cv_extraction_schema_v1',
                'created_at' => '2026-10-01 00:05:00+00',
                'updated_at' => '2026-10-01 00:05:00+00',
            ]);

            DB::table('ai_match_explanations')->insertOrIgnore([
                'organisation_id' => $organisation->getKey(),
                'job_id' => $job->getKey(),
                'candidate_id' => $candidate->getKey(),
                'requested_by_user_id' => $user->getKey(),
                'source_fingerprint' => hash('sha256', 'predeployment-match-factors'),
                'status' => 'queued',
                'provider' => 'fake',
                'model' => 'deterministic-fake-v1',
                'prompt_version' => 'match_explanation_prompt_v1',
                'schema_version' => 'match_explanation_schema_v1',
                'created_at' => '2026-10-01 00:10:00+00',
                'updated_at' => '2026-10-01 00:10:00+00',
            ]);

            if (QualificationDefinition::query()->where('organisation_id', $organisation->getKey())->count() === 0) {
                throw new RuntimeException('The synthetic qualification fixture was not created.');
            }
        });
    }
}
