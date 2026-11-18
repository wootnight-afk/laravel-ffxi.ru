<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two independent profile descriptions (STAGE-1 §17).
 *
 * Expand-only: four nullable columns next to the existing `legend` and
 * `legend_html`. The old pair stays untouched until the owner confirms the
 * split, so a wrong language guess never destroys text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('legend_ru')->nullable()->after('legend_html');
            $table->text('legend_ru_html')->nullable()->after('legend_ru');
            $table->text('legend_en')->nullable()->after('legend_ru_html');
            $table->text('legend_en_html')->nullable()->after('legend_en');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['legend_ru', 'legend_ru_html', 'legend_en', 'legend_en_html']);
        });
    }
};
