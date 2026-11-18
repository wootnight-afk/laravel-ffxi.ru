<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Game characters (ADR-012 §2.2).
 *
 * A character is an independent game entity owned by one site account and
 * bound to one game server. Name uniqueness is scoped to the server and is
 * case-insensitive: MySQL's utf8mb4_unicode_ci collation makes the plain
 * unique index case-insensitive, so "Bugor" and "bugor" collide on one server
 * while the same name may exist on different servers.
 *
 * Soft deletes keep the row for future Linkshell membership and event roster
 * history; the character disappears from every public listing immediately.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_server_id')->constrained('game_servers')->restrictOnDelete();
            $table->string('name', 24);
            $table->boolean('is_public')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['game_server_id', 'name']);
            $table->index(['user_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_characters');
    }
};
