<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();

            // Actor: the user who performed the action.
            // Nullable + nullOnDelete so that hard-deleting a user
            // does not wipe the audit trail; attribution is lost
            // but the record persists.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Action code, e.g. 'password.changed', 'email.changed'.
            $table->string('action', 64);

            // Target object (morph). For self-actions user_id == subject_id.
            $table->string('subject_type', 191)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // Change payload. Never contains secrets (no passwords, no hashes).
            $table->json('old')->nullable();
            $table->json('new')->nullable();

            $table->string('ip', 45)->nullable();           // IPv4 or IPv6
            $table->string('user_agent', 255)->nullable();

            // Immutable log: created_at only, no updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->index('action');
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
