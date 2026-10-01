<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope', 10)->default('site'); // site | player
            $table->string('title', 150);
            $table->string('slug', 180)->unique();
            $table->text('excerpt')->nullable();
            $table->mediumText('body');
            $table->mediumText('body_html')->nullable();
            $table->string('cover_path', 255)->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('comments_enabled')->default(true);
            $table->string('status', 20)->default('draft'); // draft | pending | published | rejected | archived
            $table->string('rejection_reason', 300)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['scope', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
