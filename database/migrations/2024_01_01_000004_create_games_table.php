<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_room_id')->unique();
            $table->enum('status', ['waiting', 'active', 'finished', 'cancelled'])->default('waiting');
            $table->decimal('stake_amount', 10, 2)->default(0.00);
            $table->decimal('platform_fee', 10, 2)->default(0.00);
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};