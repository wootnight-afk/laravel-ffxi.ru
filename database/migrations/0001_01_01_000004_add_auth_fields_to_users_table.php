<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('rank_id')
                ->nullable()
                ->constrained('user_ranks')
                ->nullOnDelete();

            $table->string('phone', 32)->nullable();
            $table->boolean('phone_is_public')->default(false);
            $table->string('avatar_path', 255)->nullable();
            $table->boolean('is_profile_public')->default(false);
            $table->string('race', 20)->nullable();
            $table->string('main_job', 10)->nullable();
            $table->text('legend')->nullable();
            $table->text('legend_html')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_activity_seen_at')->nullable();
            $table->timestamp('chat_banned_until')->nullable();
            $table->timestamp('banned_until')->nullable();
            $table->string('ban_reason', 200)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamp('deletion_requested_at')->nullable();
            $table->string('deletion_reason', 500)->nullable();
            $table->timestamp('pd_consent_at')->nullable();
            $table->string('pd_policy_version', 20)->nullable();
            $table->timestamp('marketing_consent_at')->nullable();
        });

        // Уникальный ник, независимый от регистра: collation колонки -> utf8mb4_unicode_ci.
        // Отдельный запрос, т.к. Blueprint не умеет менять collation существующей колонки.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE `users` MODIFY `name` VARCHAR(255) '
                .'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_name_unique');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            $collation = (string) config('database.connections.mysql.collation', 'utf8mb4_unicode_ci');

            DB::statement(
                'ALTER TABLE `users` MODIFY `name` VARCHAR(255) '
                ."CHARACTER SET utf8mb4 COLLATE {$collation} NOT NULL"
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rank_id');
            $table->dropColumn([
                'phone',
                'phone_is_public',
                'avatar_path',
                'is_profile_public',
                'race',
                'main_job',
                'legend',
                'legend_html',
                'last_seen_at',
                'last_activity_seen_at',
                'chat_banned_until',
                'banned_until',
                'ban_reason',
                'status',
                'deletion_requested_at',
                'deletion_reason',
                'pd_consent_at',
                'pd_policy_version',
                'marketing_consent_at',
            ]);
        });
    }
};
