<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('color', ['red', 'green', 'yellow', 'blue']);
            $table->enum('result', ['winner', 'loser', 'abandoned'])->nullable();
            $table->integer('final_position')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'user_id']);
            $table->unique(['game_id', 'color']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_players');
    }
};