<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('current_turn_user_id')
                ->nullable()
                ->after('winner_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->unsignedInteger('turn_number')
                ->default(0)
                ->after('current_turn_user_id');

            $table->string('phase')
                ->nullable()
                ->after('turn_number');

            $table->unsignedTinyInteger('dice_roll')
                ->nullable()
                ->after('phase');

            $table->unsignedBigInteger('state_version')
                ->default(0)
                ->after('dice_roll');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['current_turn_user_id']);
            $table->dropColumn([
                'current_turn_user_id',
                'turn_number',
                'phase',
                'dice_roll',
                'state_version',
            ]);
        });
    }
};