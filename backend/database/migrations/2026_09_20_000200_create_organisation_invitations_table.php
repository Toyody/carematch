<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')
                ->constrained('organisations')
                ->restrictOnDelete();
            $table->string('email');
            $table->string('role', 32);
            $table->string('token_hash', 64)->unique();
            $table->unsignedBigInteger('invited_by_user_id');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign(
                ['organisation_id', 'invited_by_user_id'],
                'org_invitations_inviter_membership_fk',
            )->references(['organisation_id', 'user_id'])
                ->on('organisation_memberships')
                ->restrictOnDelete();
            $table->index(
                ['organisation_id', 'email'],
                'org_invitations_org_email_index',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE organisation_invitations
            ADD CONSTRAINT organisation_invitations_role_check
            CHECK (role IN ('admin', 'recruiter', 'hiring_manager'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE organisation_invitations
            ADD CONSTRAINT organisation_invitations_email_normalised_check
            CHECK (email = lower(btrim(email)))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE organisation_invitations
            ADD CONSTRAINT organisation_invitations_state_check
            CHECK (accepted_at IS NULL OR revoked_at IS NULL)
            SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX org_invitations_unresolved_unique
            ON organisation_invitations (organisation_id, email)
            WHERE accepted_at IS NULL AND revoked_at IS NULL
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_invitations');
    }
};
