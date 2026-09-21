<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('candidate_id');
            $table->string('status', 20)->default('applied');
            $table->timestampTz('applied_at');
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestampsTz();

            $table->unique(['organisation_id', 'id']);
            $table->unique(
                ['organisation_id', 'job_id', 'candidate_id'],
                'applications_org_job_candidate_unique',
            );
            $table->foreign(['organisation_id', 'job_id'], 'applications_organisation_job_foreign')
                ->references(['organisation_id', 'id'])->on('jobs')->restrictOnDelete();
            $table->foreign(['organisation_id', 'candidate_id'], 'applications_organisation_candidate_foreign')
                ->references(['organisation_id', 'id'])->on('candidates')->restrictOnDelete();
            $table->foreign(
                ['organisation_id', 'created_by_user_id'],
                'applications_organisation_creator_membership_foreign',
            )->references(['organisation_id', 'user_id'])->on('organisation_memberships')->restrictOnDelete();
            $table->index(['organisation_id', 'candidate_id']);
            $table->index(['organisation_id', 'status', 'applied_at', 'id']);
            $table->index(['organisation_id', 'applied_at', 'id']);
            $table->index(['organisation_id', 'updated_at', 'id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE applications
            ADD CONSTRAINT applications_status_check
            CHECK (status IN ('applied', 'screening', 'interview', 'offer', 'hired', 'rejected'))
            SQL);

        Schema::create('application_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('application_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->unsignedBigInteger('changed_by_user_id');
            $table->text('note')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign(
                ['organisation_id', 'application_id'],
                'application_history_organisation_application_foreign',
            )->references(['organisation_id', 'id'])->on('applications')->restrictOnDelete();
            $table->foreign(
                ['organisation_id', 'changed_by_user_id'],
                'application_history_organisation_actor_membership_foreign',
            )->references(['organisation_id', 'user_id'])->on('organisation_memberships')->restrictOnDelete();
            $table->index(['organisation_id', 'application_id', 'created_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE application_status_history
            ADD CONSTRAINT application_history_from_status_check
            CHECK (from_status IS NULL OR from_status IN ('applied', 'screening', 'interview', 'offer', 'hired', 'rejected'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE application_status_history
            ADD CONSTRAINT application_history_to_status_check
            CHECK (to_status IN ('applied', 'screening', 'interview', 'offer', 'hired', 'rejected'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('application_status_history');
        Schema::dropIfExists('applications');
    }
};
