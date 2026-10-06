<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restore_requests', function (Blueprint $table) {
            // UUID primary key (ADR-004 R5): the id is the capability shown to
            // the admin and passed to the CLI, never an auto-increment value.
            $table->uuid('id')->primary();

            // Backup to restore, referenced by manifest backup_id (a UUID).
            $table->string('backup_id', 64);

            // Admin who requested the restore. The request is useless without
            // the requesting admin, so it is removed with a hard delete.
            $table->foreignId('admin_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // HMAC-SHA256 over (request_id|backup_id|admin_user_id|expires_at)
            // keyed by APP_KEY (ADR-004 R5). Detects row tampering.
            $table->string('token', 64);

            $table->timestamp('expires_at');

            // pending | applied | failed.
            $table->string('status', 16)->default('pending');

            $table->timestamp('consumed_at')->nullable();

            $table->timestamps();

            $table->index('backup_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restore_requests');
    }
};
