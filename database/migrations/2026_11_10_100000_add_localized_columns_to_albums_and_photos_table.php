<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RU/EN content for gallery albums and photos (ADR-011 §2.20).
 *
 * All English columns are nullable without backfill: an empty value means
 * "use the Russian one" (per-field fallback in GalleryController and
 * PlayerGalleryController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->string('title_en', 120)->nullable()->after('title');
            $table->text('description_en')->nullable()->after('description');
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->string('caption_en', 200)->nullable()->after('caption');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn(['caption_en']);
        });

        Schema::table('albums', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'description_en']);
        });
    }
};
