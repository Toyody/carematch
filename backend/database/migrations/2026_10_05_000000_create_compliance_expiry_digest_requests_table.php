<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_expiry_digest_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->char('idempotency_key_hash', 64);
            $table->char('request_fingerprint', 64);
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('expired_count')->nullable();
            $table->unsignedInteger('expiring_count')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestampTz('queued_at');
            $table->timestampTz('processing_started_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['organisation_id', 'requested_by_user_id', 'idempotency_key_hash'],
                'compliance_expiry_digest_requests_idempotency_unique',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE compliance_expiry_digest_requests
            ADD CONSTRAINT compliance_expiry_digest_requests_status_check
            CHECK (status IN ('queued', 'processing', 'sent', 'failed'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE compliance_expiry_digest_requests
            ADD CONSTRAINT compliance_expiry_digest_requests_state_check
            CHECK (
                (status = 'queued' AND processing_started_at IS NULL AND sent_at IS NULL AND failed_at IS NULL
                    AND expired_count IS NULL AND expiring_count IS NULL AND failure_code IS NULL)
                OR (status = 'processing' AND processing_started_at IS NOT NULL AND sent_at IS NULL AND failed_at IS NULL
                    AND expired_count IS NULL AND expiring_count IS NULL AND failure_code IS NULL)
                OR (status = 'sent' AND processing_started_at IS NOT NULL AND sent_at IS NOT NULL AND failed_at IS NULL
                    AND expired_count IS NOT NULL AND expiring_count IS NOT NULL AND failure_code IS NULL)
                OR (status = 'failed' AND processing_started_at IS NOT NULL AND sent_at IS NULL AND failed_at IS NOT NULL
                    AND expired_count IS NULL AND expiring_count IS NULL AND failure_code IS NOT NULL)
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE compliance_expiry_digest_requests
            ADD CONSTRAINT compliance_expiry_digest_requests_counts_check
            CHECK (
                (expired_count IS NULL OR expired_count >= 0)
                AND (expiring_count IS NULL OR expiring_count >= 0)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_expiry_digest_requests');
    }
};
