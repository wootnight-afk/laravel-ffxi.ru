<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job levels of a character (ADR-013 §2.8).
 *
 * A separate table instead of a JSON column: unique constraint per job,
 * indexable lookups for the future event roster, and no validation "inside a
 * string". Rows survive a soft delete of the character and are removed by the
 * foreign key only on a physical delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_character_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_character_id')->constrained()->cascadeOnDelete();
            $table->string('job', 10);
            $table->unsignedTinyInteger('level');
            $table->timestamps();

            $table->unique(['game_character_id', 'job']);
            $table->index(['job', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_character_jobs');
    }
};
