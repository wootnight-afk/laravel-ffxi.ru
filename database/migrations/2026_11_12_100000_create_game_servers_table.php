<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Game servers reference table (ADR-012 §2.1).
 *
 * The list of servers lives in the database, never in PHP code: a new server
 * is added by an admin, an obsolete one is deactivated instead of deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('slug', 50)->unique();
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_servers');
    }
};
