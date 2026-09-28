<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualification_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $table->string('name', 200);
            $table->string('category', 100)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['organisation_id', 'id']);
            $table->index(['organisation_id', 'is_active', 'name']);
        });

        Schema::create('candidate_qualifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('candidate_id');
            $table->unsignedBigInteger('qualification_definition_id');
            $table->string('issuer', 200)->nullable();
            $table->string('credential_number', 255)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->timestampsTz();

            $table->unique(['organisation_id', 'id']);
            $table->foreign(['organisation_id', 'candidate_id'], 'candidate_qualifications_organisation_candidate_foreign')
                ->references(['organisation_id', 'id'])->on('candidates')->restrictOnDelete();
            $table->foreign(['organisation_id', 'qualification_definition_id'], 'candidate_qualifications_organisation_definition_foreign')
                ->references(['organisation_id', 'id'])->on('qualification_definitions')->restrictOnDelete();
            $table->index(['organisation_id', 'candidate_id', 'qualification_definition_id'], 'candidate_qualifications_candidate_definition_index');
            $table->index(['organisation_id', 'expires_on'], 'candidate_qualifications_expiry_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE candidate_qualifications
            ADD CONSTRAINT candidate_qualifications_dates_check
            CHECK (issued_on IS NULL OR expires_on IS NULL OR issued_on <= expires_on)
            SQL);

        Schema::create('job_qualification_requirements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('qualification_definition_id');
            $table->timestampsTz();

            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'job_id', 'qualification_definition_id'], 'job_qualification_requirements_unique');
            $table->foreign(['organisation_id', 'job_id'], 'job_qualification_requirements_organisation_job_foreign')
                ->references(['organisation_id', 'id'])->on('jobs')->restrictOnDelete();
            $table->foreign(['organisation_id', 'qualification_definition_id'], 'job_qualification_requirements_organisation_definition_foreign')
                ->references(['organisation_id', 'id'])->on('qualification_definitions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_qualification_requirements');
        Schema::dropIfExists('candidate_qualifications');
        Schema::dropIfExists('qualification_definitions');
    }
};
