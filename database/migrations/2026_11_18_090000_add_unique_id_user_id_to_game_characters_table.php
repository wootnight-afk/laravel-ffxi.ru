<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite reference key for the active character pointer (STAGE-2 §3).
 *
 * `user_active_characters` references the pair (id, user_id) of a character so
 * that the database itself rejects an active pointer to a character owned by
 * another account. MySQL 8.4 runs with `restrict_fk_on_non_standard_key = ON`,
 * therefore the referenced columns must be covered by a UNIQUE key: a plain
 * index is rejected with errno 6125. The key is logically tautological — the
 * uniqueness is already guaranteed by PRIMARY KEY (id) — but MySQL requires a
 * unique key as the anchor for such a foreign key, so it is a schema
 * requirement rather than an extra domain constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->unique(['id', 'user_id'], 'game_characters_id_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->dropUnique('game_characters_id_user_id_unique');
        });
    }
};
