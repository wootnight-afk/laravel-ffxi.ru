<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two independent legends per character (STAGE-1 §16).
 *
 * Expand-only: four nullable columns. `legend` and `legend_html` are NOT
 * touched and NOT dropped: they stay the source of the one-off redistribution
 * and the safety net if a guess turns out to be wrong. Dropping them is a
 * separate decision after the owner has checked the split.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->text('legend_ru')->nullable()->after('legend_html');
            $table->text('legend_ru_html')->nullable()->after('legend_ru');
            $table->text('legend_en')->nullable()->after('legend_ru_html');
            $table->text('legend_en_html')->nullable()->after('legend_en');
        });
    }

    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->dropColumn(['legend_ru', 'legend_ru_html', 'legend_en', 'legend_en_html']);
        });
    }
};
