<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Game data of a character (ADR-013 §2.8).
 *
 * Expand-only: four nullable columns and one index. Nothing is backfilled and
 * no existing column is touched, so `users.race` / `users.main_job` /
 * `users.legend*` stay exactly as they are; the legacy transfer is a separate
 * operation with its own confirmation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->string('race', 20)->nullable()->after('name');
            $table->string('main_job', 10)->nullable()->after('race');
            $table->text('legend')->nullable()->after('main_job');
            $table->text('legend_html')->nullable()->after('legend');

            $table->index('main_job');
        });
    }

    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->dropIndex(['main_job']);
            $table->dropColumn(['race', 'main_job', 'legend', 'legend_html']);
        });
    }
};
