<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE INDEX application_history_org_created_index
            ON application_status_history (organisation_id, created_at DESC, id DESC)
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS application_history_org_created_index');
    }
};
