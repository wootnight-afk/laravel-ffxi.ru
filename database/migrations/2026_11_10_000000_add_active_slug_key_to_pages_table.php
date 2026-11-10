<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep the slug of an active page unique while letting a soft-deleted page
     * release its slug.
     *
     * MySQL treats NULL values as distinct inside a UNIQUE index, so
     * `UNIQUE(slug, deleted_at)` would still accept two active pages with the
     * same slug. The generated column holds the slug while the page is active
     * and NULL once it is soft-deleted, which is exactly the constraint the
     * application needs.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->string('slug_key', 120)
                ->nullable()
                ->storedAs('if(`deleted_at` is null, `slug`, null)')
                ->after('slug');
            $table->unique('slug_key', 'pages_slug_key_unique');
        });

        Schema::table('pages', function (Blueprint $table): void {
            $table->dropUnique('pages_slug_unique');
        });
    }

    /**
     * Restoring `UNIQUE(slug)` fails while two soft-deleted pages share a slug:
     * resolve the duplicates before rolling this migration back.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->unique('slug', 'pages_slug_unique');
        });

        Schema::table('pages', function (Blueprint $table): void {
            $table->dropUnique('pages_slug_key_unique');
            $table->dropColumn('slug_key');
        });
    }
};
