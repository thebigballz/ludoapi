<?php

namespace Tests\Feature\Game;

use App\Domain\Game\Actions\RollDice;
use App\Domain\Game\Exceptions\InvalidGameStateException;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerAuthoritativeRollTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_player_can_roll_the_dice(): void
    {
        $user = User::factory()->create();

        $game = Game::factory()->create([
            'status' => 'active',
            'current_turn_user_id' => $user->id,
            'turn_number' => 1,
            'phase' => 'rolling',
            'dice_roll' => null,
            'state_version' => 1,
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'color' => 'red',
        ]);

        $roll = app(RollDice::class)->execute($game, $user);

        $this->assertGreaterThanOrEqual(1, $roll);
        $this->assertLessThanOrEqual(6, $roll);

        $game->refresh();

        $this->assertSame($roll, $game->dice_roll);
        $this->assertSame('moving', $game->phase);
        $this->assertSame(2, $game->state_version);
    }

    public function test_player_cannot_roll_when_it_is_not_their_turn(): void
    {
        $currentPlayer = User::factory()->create();
        $otherPlayer = User::factory()->create();

        $game = Game::factory()->create([
            'status' => 'active',
            'current_turn_user_id' => $currentPlayer->id,
            'turn_number' => 1,
            'phase' => 'rolling',
            'state_version' => 1,
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $currentPlayer->id,
            'color' => 'red',
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $otherPlayer->id,
            'color' => 'blue',
        ]);

        $this->expectException(InvalidGameStateException::class);
        $this->expectExceptionMessage('It is not your turn.');

        app(RollDice::class)->execute($game, $otherPlayer);
    }

    public function test_player_cannot_roll_when_game_is_not_active(): void
    {
        $user = User::factory()->create();

        $game = Game::factory()->create([
            'status' => 'waiting',
            'current_turn_user_id' => $user->id,
            'turn_number' => 0,
            'phase' => null,
            'dice_roll' => null,
            'state_version' => 0,
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'color' => 'red',
        ]);

        $this->expectException(InvalidGameStateException::class);
        $this->expectExceptionMessage('This game is not active.');

        app(RollDice::class)->execute($game, $user);
    }

    public function test_player_cannot_roll_twice_in_the_same_turn(): void
    {
        $user = User::factory()->create();

        $game = Game::factory()->create([
            'status' => 'active',
            'current_turn_user_id' => $user->id,
            'turn_number' => 1,
            'phase' => 'rolling',
            'dice_roll' => null,
            'state_version' => 1,
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'color' => 'red',
        ]);

        $roll = app(RollDice::class)->execute($game, $user);

        $this->assertGreaterThanOrEqual(1, $roll);
        $this->assertLessThanOrEqual(6, $roll);

        $this->expectException(InvalidGameStateException::class);
        $this->expectExceptionMessage(
            'The game is not currently accepting a dice roll.'
        );

        app(RollDice::class)->execute($game->fresh(), $user);
    }
}