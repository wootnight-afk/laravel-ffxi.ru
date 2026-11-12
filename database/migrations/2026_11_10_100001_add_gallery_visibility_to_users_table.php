<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separate visibility switch for the personal player gallery.
 *
 * Defaults to true so that the current behaviour (gallery visible whenever the
 * profile is public) is preserved for existing accounts. It complements —
 * never replaces — users.is_profile_public: a public gallery is impossible
 * while the profile itself is private.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_gallery_public')->default(true)->after('is_profile_public');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_gallery_public']);
        });
    }
};
