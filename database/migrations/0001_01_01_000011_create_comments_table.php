<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('commentable_type', 60);
            $table->unsignedBigInteger('commentable_id');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 20)->default('pending'); // approved | pending | rejected | spam
            $table->boolean('is_reported')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['commentable_type', 'commentable_id', 'status', 'created_at'], 'comments_target_idx');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
