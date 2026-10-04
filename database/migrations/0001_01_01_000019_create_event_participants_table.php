<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('jobs', 100)->nullable();
            $table->string('note', 200)->nullable();
            $table->enum('status', ['joined', 'left']);
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();

            $table->unique(['event_id', 'user_id']);
            $table->index('user_id');
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_participants');
    }
};
