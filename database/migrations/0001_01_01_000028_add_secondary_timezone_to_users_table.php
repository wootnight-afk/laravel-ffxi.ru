<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secondary clock of the admin dashboard (ADR-011 §2.25).
 *
 * Nullable without a default or backfill: NULL means "do not show the second
 * clock", so existing users keep the current behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('secondary_timezone', 64)->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('secondary_timezone');
        });
    }
};
