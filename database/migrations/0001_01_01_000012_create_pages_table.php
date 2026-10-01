<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('slug', 120)->unique();
            $table->mediumText('body');
            $table->mediumText('body_html')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('show_in_menu')->default(false);
            $table->unsignedSmallInteger('menu_order')->default(0);
            $table->string('meta_title', 180)->nullable();
            $table->string('meta_description', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
