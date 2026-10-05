<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_cv_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $table->unsignedBigInteger('candidate_id');
            // Deliberately not a FK: deleting a source document must not delete or block
            // an immutable review record, and a queued worker must fail safely by ID.
            $table->unsignedBigInteger('candidate_document_id');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('applied_by_user_id')->nullable();
            $table->string('idempotency_key_hash', 64);
            $table->string('request_fingerprint', 64);
            $table->string('status', 32);
            $table->string('provider', 50);
            $table->string('model', 100);
            $table->string('prompt_version', 50);
            $table->string('schema_version', 50);
            $table->jsonb('draft')->nullable();
            $table->timestampTz('candidate_version')->nullable();
            $table->string('candidate_fingerprint', 64)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->string('provider_request_id', 255)->nullable();
            $table->timestampTz('processing_started_at')->nullable();
            $table->timestampTz('review_ready_at')->nullable();
            $table->timestampTz('applied_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organisation_id', 'id']);
            $table->unique(
                ['organisation_id', 'requested_by_user_id', 'idempotency_key_hash'],
                'ai_cv_extractions_org_user_idempotency_unique',
            );
            $table->foreign(['organisation_id', 'candidate_id'], 'ai_cv_extractions_org_candidate_foreign')
                ->references(['organisation_id', 'id'])->on('candidates')->restrictOnDelete();
            $table->foreign(['organisation_id', 'requested_by_user_id'], 'ai_cv_extractions_org_requester_foreign')
                ->references(['organisation_id', 'user_id'])->on('organisation_memberships')->restrictOnDelete();
            $table->foreign(['organisation_id', 'applied_by_user_id'], 'ai_cv_extractions_org_applier_foreign')
                ->references(['organisation_id', 'user_id'])->on('organisation_memberships')->restrictOnDelete();
            $table->index(['organisation_id', 'candidate_id', 'created_at'], 'ai_cv_extractions_org_candidate_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ai_cv_extractions
            ADD CONSTRAINT ai_cv_extractions_status_check
            CHECK (status IN ('queued', 'processing', 'review_ready', 'applied', 'failed'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE ai_cv_extractions
            ADD CONSTRAINT ai_cv_extractions_lifecycle_check
            CHECK (
                (status = 'queued' AND draft IS NULL AND review_ready_at IS NULL AND applied_by_user_id IS NULL AND applied_at IS NULL AND failure_code IS NULL AND failed_at IS NULL)
                OR (status = 'processing' AND processing_started_at IS NOT NULL AND draft IS NULL AND review_ready_at IS NULL AND applied_by_user_id IS NULL AND applied_at IS NULL AND failure_code IS NULL AND failed_at IS NULL)
                OR (status = 'review_ready' AND draft IS NOT NULL AND candidate_version IS NOT NULL AND candidate_fingerprint IS NOT NULL AND review_ready_at IS NOT NULL AND applied_by_user_id IS NULL AND applied_at IS NULL AND failure_code IS NULL AND failed_at IS NULL)
                OR (status = 'applied' AND draft IS NOT NULL AND candidate_version IS NOT NULL AND candidate_fingerprint IS NOT NULL AND review_ready_at IS NOT NULL AND applied_by_user_id IS NOT NULL AND applied_at IS NOT NULL AND failure_code IS NULL AND failed_at IS NULL)
                OR (status = 'failed' AND applied_by_user_id IS NULL AND applied_at IS NULL AND failure_code IS NOT NULL AND failed_at IS NOT NULL)
            )
            SQL);

        Schema::create('ai_match_explanations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('candidate_id');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->string('source_fingerprint', 64);
            $table->string('status', 32);
            $table->string('provider', 50);
            $table->string('model', 100);
            $table->string('prompt_version', 50);
            $table->string('schema_version', 50);
            $table->string('summary', 600)->nullable();
            $table->jsonb('factors')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->string('provider_request_id', 255)->nullable();
            $table->timestampTz('processing_started_at')->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organisation_id', 'id']);
            $table->unique(
                ['organisation_id', 'job_id', 'candidate_id', 'source_fingerprint'],
                'ai_match_explanations_current_source_unique',
            );
            $table->foreign(['organisation_id', 'job_id'], 'ai_match_explanations_org_job_foreign')
                ->references(['organisation_id', 'id'])->on('jobs')->restrictOnDelete();
            $table->foreign(['organisation_id', 'candidate_id'], 'ai_match_explanations_org_candidate_foreign')
                ->references(['organisation_id', 'id'])->on('candidates')->restrictOnDelete();
            $table->foreign(['organisation_id', 'requested_by_user_id'], 'ai_match_explanations_org_requester_foreign')
                ->references(['organisation_id', 'user_id'])->on('organisation_memberships')->restrictOnDelete();
            $table->index(['organisation_id', 'job_id', 'created_at'], 'ai_match_explanations_org_job_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ai_match_explanations
            ADD CONSTRAINT ai_match_explanations_status_check
            CHECK (status IN ('queued', 'processing', 'ready', 'failed'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE ai_match_explanations
            ADD CONSTRAINT ai_match_explanations_lifecycle_check
            CHECK (
                (status = 'queued' AND summary IS NULL AND factors IS NULL AND ready_at IS NULL AND failure_code IS NULL AND failed_at IS NULL)
                OR (status = 'processing' AND processing_started_at IS NOT NULL AND summary IS NULL AND factors IS NULL AND ready_at IS NULL AND failure_code IS NULL AND failed_at IS NULL)
                OR (status = 'ready' AND summary IS NOT NULL AND factors IS NOT NULL AND ready_at IS NOT NULL AND failure_code IS NULL AND failed_at IS NULL)
                OR (status = 'failed' AND summary IS NULL AND factors IS NULL AND ready_at IS NULL AND failure_code IS NOT NULL AND failed_at IS NOT NULL)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_match_explanations');
        Schema::dropIfExists('ai_cv_extractions');
    }
};
