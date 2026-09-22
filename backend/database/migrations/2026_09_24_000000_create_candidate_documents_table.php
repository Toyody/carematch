<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')
                ->constrained('organisations')
                ->restrictOnDelete();
            $table->unsignedBigInteger('candidate_id');
            $table->string('original_name');
            $table->string('storage_key', 64)->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedBigInteger('uploaded_by_user_id');
            $table->timestampsTz();

            $table->unique(['organisation_id', 'id']);
            $table->foreign(['organisation_id', 'candidate_id'], 'candidate_documents_organisation_candidate_foreign')
                ->references(['organisation_id', 'id'])
                ->on('candidates')
                ->restrictOnDelete();
            $table->foreign(['organisation_id', 'uploaded_by_user_id'], 'candidate_documents_organisation_uploader_foreign')
                ->references(['organisation_id', 'user_id'])
                ->on('organisation_memberships')
                ->restrictOnDelete();
            $table->index(
                ['organisation_id', 'candidate_id', 'created_at', 'id'],
                'candidate_documents_org_candidate_created_index',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE candidate_documents
            ADD CONSTRAINT candidate_documents_size_bytes_check
            CHECK (size_bytes > 0)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_documents');
    }
};
