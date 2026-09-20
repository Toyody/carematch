<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')
                ->constrained('organisations')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('role', 32);
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'user_id']);
            $table->unique(['organisation_id', 'id']);
            $table->index(['user_id', 'organisation_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE organisation_memberships
            ADD CONSTRAINT organisation_memberships_role_check
            CHECK (role IN ('admin', 'recruiter', 'hiring_manager'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_memberships');
    }
};
