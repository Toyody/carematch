<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')
                ->constrained('organisations')
                ->restrictOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('occupation')->nullable();
            $table->string('location')->nullable();
            $table->string('availability')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'id']);
            $table->index(['organisation_id', 'created_at']);
            $table->index(['organisation_id', 'last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
