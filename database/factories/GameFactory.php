<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        return [
            'firebase_room_id' => fake()->uuid(),
            'status' => 'waiting',
            'stake_amount' => '100.00',
            'platform_fee' => '10.00',
            'winner_id' => null,
            'current_turn_user_id' => null,
            'turn_number' => 0,
            'phase' => null,
            'dice_roll' => null,
            'state_version' => 0,
            'started_at' => null,
            'ended_at' => null,
        ];
    }
}
