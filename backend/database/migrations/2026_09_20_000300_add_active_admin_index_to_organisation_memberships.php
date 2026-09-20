<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE INDEX org_memberships_active_admin_index
            ON organisation_memberships (organisation_id)
            WHERE role = 'admin' AND deactivated_at IS NULL
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS org_memberships_active_admin_index');
    }
};
