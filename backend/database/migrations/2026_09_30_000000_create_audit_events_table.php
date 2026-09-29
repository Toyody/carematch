<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('event_type', 100);
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestampTz('occurred_at');

            $table->foreign(['organisation_id', 'actor_user_id'])
                ->references(['organisation_id', 'user_id'])
                ->on('organisation_memberships')
                ->restrictOnDelete();
        });

        DB::statement('CREATE INDEX audit_events_tenant_occurred_index ON audit_events (organisation_id, occurred_at DESC, id DESC)');
        DB::statement('CREATE INDEX audit_events_tenant_event_index ON audit_events (organisation_id, event_type, occurred_at DESC, id DESC)');
        DB::statement('CREATE INDEX audit_events_tenant_actor_index ON audit_events (organisation_id, actor_user_id, occurred_at DESC, id DESC)');

        DB::statement(<<<'SQL'
            ALTER TABLE audit_events
            ADD CONSTRAINT audit_events_event_type_not_blank CHECK (event_type = btrim(event_type) AND event_type <> ''),
            ADD CONSTRAINT audit_events_subject_type_not_blank CHECK (subject_type = btrim(subject_type) AND subject_type <> ''),
            ADD CONSTRAINT audit_events_subject_id_positive CHECK (subject_id > 0),
            ADD CONSTRAINT audit_events_metadata_object CHECK (jsonb_typeof(metadata) = 'object')
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_audit_event_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'audit_events are append-only';
            END;
            $$;

            CREATE TRIGGER audit_events_append_only
            BEFORE UPDATE OR DELETE ON audit_events
            FOR EACH ROW
            EXECUTE FUNCTION reject_audit_event_mutation();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        DB::statement('DROP FUNCTION IF EXISTS reject_audit_event_mutation()');
    }
};
