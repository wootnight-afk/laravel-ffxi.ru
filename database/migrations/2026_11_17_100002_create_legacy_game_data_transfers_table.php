<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit journal of the legacy game data transfer (ADR-013 §2.10).
 *
 * The journal records both sides of the transfer: the account value that was
 * the source and the character value that was the target, plus a control hash
 * of what was actually written. A rollback restores `target_value_before` only
 * when the field still matches `applied_hash`, so a user edit made after the
 * transfer is never overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_game_data_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained('game_characters')->cascadeOnDelete();
            $table->string('field', 32);
            $table->string('field_group', 16)->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('source_value')->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->text('target_value_before')->nullable();
            $table->string('target_hash_before', 64)->nullable();
            $table->string('applied_hash', 64)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status']);
            $table->index(['character_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_game_data_transfers');
    }
};
