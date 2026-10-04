<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('type_id')->nullable()->constrained('event_types')->nullOnDelete();
            $table->string('title', 150);
            $table->text('description');
            $table->string('location', 120)->nullable();
            $table->dateTime('starts_at');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedSmallInteger('max_participants')->nullable();
            $table->timestamp('registration_close')->nullable();
            $table->enum('status', ['planned', 'completed', 'cancelled']);
            $table->timestamps();
            $table->softDeletes();

            $table->index('starts_at');
            $table->index('type_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
