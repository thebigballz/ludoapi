<?php

namespace Tests\Feature\Game;

use App\Domain\Game\Actions\SkipTurn;
use App\Domain\Game\Exceptions\InvalidGameStateException;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerAuthoritativeSkipTurnTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_player_can_skip_when_no_legal_move_exists(): void
    {
        $currentPlayer = User::factory()->create();
        $nextPlayer = User::factory()->create();

        $game = Game::factory()->create([
            'status' => 'active',
            'current_turn_user_id' => $currentPlayer->id,
            'turn_number' => 1,
            'phase' => 'moving',
            'dice_roll' => 3,
            'state_version' => 4,
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $currentPlayer->id,
            'color' => 'red',
            'pawn_positions' => [57, 57, 57, 57],
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $nextPlayer->id,
            'color' => 'blue',
            'pawn_positions' => [-1, -1, -1, -1],
        ]);

        $result = app(SkipTurn::class)->execute($game, $currentPlayer);

        $this->assertSame($nextPlayer->id, $result->current_turn_user_id);
        $this->assertSame(2, $result->turn_number);
        $this->assertSame('rolling', $result->phase);
        $this->assertNull($result->dice_roll);
        $this->assertSame(5, $result->state_version);
    }

    public function test_skip_is_rejected_when_a_legal_move_exists(): void
    {
        $user = User::factory()->create();

        $game = Game::factory()->create([
            'status' => 'active',
            'current_turn_user_id' => $user->id,
            'turn_number' => 1,
            'phase' => 'moving',
            'dice_roll' => 6,
            'state_version' => 1,
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'color' => 'red',
            'pawn_positions' => [-1, 57, 57, 57],
        ]);

        $this->expectException(InvalidGameStateException::class);
        $this->expectExceptionMessage('A legal pawn move is available.');

        app(SkipTurn::class)->execute($game, $user);
    }
}
