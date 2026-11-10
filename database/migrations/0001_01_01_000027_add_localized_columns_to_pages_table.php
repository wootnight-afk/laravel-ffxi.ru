<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RU/EN CMS content for static pages (ADR-011 §2.20, §8.2 Q-L6-2).
 *
 * All English columns are nullable without backfill: an empty value means
 * "use the Russian one" (per-field fallback in PageController::show).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('title_en', 150)->nullable()->after('title');
            $table->mediumText('body_en')->nullable()->after('body');
            $table->mediumText('body_html_en')->nullable()->after('body_html');
            $table->string('meta_title_en', 180)->nullable()->after('meta_title');
            $table->string('meta_description_en', 255)->nullable()->after('meta_description');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn([
                'title_en',
                'body_en',
                'body_html_en',
                'meta_title_en',
                'meta_description_en',
            ]);
        });
    }
};
