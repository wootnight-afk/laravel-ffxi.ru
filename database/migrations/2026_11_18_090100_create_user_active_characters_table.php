<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The active character of an account (ADR-013 §2.2, STAGE-2 §3, variant A).
 *
 * One row per account: `user_id` is the primary key. `UNIQUE (character_id)`
 * keeps a character active for at most one account. The only link to the
 * character is the composite foreign key on (character_id, user_id): it covers
 * referential integrity and the delete cascade on its own, so a separate plain
 * foreign key on `character_id` is deliberately not created. Ownership is thus
 * guaranteed by the schema, not only by application checks.
 *
 * Soft delete does not trigger a foreign key, so the pointer is also cleared by
 * GameCharacterSaver (inside the delete transaction) and by the model's
 * `deleting` observer; the resolver additionally ignores `deleted_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_active_characters', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('character_id')->unique();
            $table->timestamps();

            $table->foreign(['character_id', 'user_id'], 'user_active_characters_character_owner_foreign')
                ->references(['id', 'user_id'])
                ->on('game_characters')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_active_characters');
    }
};
