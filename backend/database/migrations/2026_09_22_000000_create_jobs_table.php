<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')
                ->constrained('organisations')
                ->restrictOnDelete();
            $table->string('title', 200);
            $table->string('occupation')->nullable();
            $table->string('location')->nullable();
            $table->string('employment_type', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('closes_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organisation_id', 'id']);
            $table->index(['organisation_id', 'status', 'created_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE jobs
            ADD CONSTRAINT jobs_status_check
            CHECK (status IN ('draft', 'open', 'closed', 'archived'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE jobs
            ADD CONSTRAINT jobs_dates_check
            CHECK (closes_at IS NULL OR opened_at IS NULL OR closes_at >= opened_at)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
