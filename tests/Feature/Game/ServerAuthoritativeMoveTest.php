<?php

namespace Tests\Feature\Game;

use App\Domain\Game\Actions\MovePawn;
use App\Domain\Game\Exceptions\InvalidGameStateException;
use App\Domain\Wallet\Actions\ReleaseEscrow;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerAuthoritativeMoveTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_move_a_pawn_from_home_on_a_six_and_keep_the_turn(): void
    {
        [$game, $currentPlayer] = $this->activeGame();

        $game->update([
            'dice_roll' => 6,
            'phase' => 'moving',
        ]);

        $this->fakeFirebase();
        $this->mock(ReleaseEscrow::class);

        $result = app(MovePawn::class)->execute($game, $currentPlayer, 0);

        $player = GamePlayer::where('game_id', $game->id)
            ->where('user_id', $currentPlayer->id)
            ->firstOrFail();

        $this->assertSame(0, $player->pawn_positions[0]);
        $this->assertSame($currentPlayer->id, $result['game']->current_turn_user_id);
        $this->assertSame('rolling', $result['game']->phase);
        $this->assertSame(1, $result['game']->turn_number);
        $this->assertSame(2, $result['game']->state_version);
    }

    public function test_player_cannot_move_a_pawn_from_home_without_a_six(): void
    {
        [$game, $currentPlayer] = $this->activeGame();

        $game->update([
            'dice_roll' => 5,
            'phase' => 'moving',
        ]);

        $this->fakeFirebase();
        $this->mock(ReleaseEscrow::class);

        $this->expectException(InvalidGameStateException::class);
        $this->expectExceptionMessage('That pawn cannot make this move.');

        app(MovePawn::class)->execute($game, $currentPlayer, 0);
    }

    public function test_player_cannot_overshoot_the_finish(): void
    {
        [$game, $currentPlayer] = $this->activeGame();

        GamePlayer::where('game_id', $game->id)
            ->where('user_id', $currentPlayer->id)
            ->update(['pawn_positions' => [55, -1, -1, -1]]);

        $game->update([
            'dice_roll' => 3,
            'phase' => 'moving',
        ]);

        $this->fakeFirebase();
        $this->mock(ReleaseEscrow::class);

        $this->expectException(InvalidGameStateException::class);
        $this->expectExceptionMessage('That pawn cannot make this move.');

        app(MovePawn::class)->execute($game, $currentPlayer, 0);
    }

    public function test_player_can_capture_on_a_non_safe_shared_square(): void
    {
        [$game, $currentPlayer, $otherPlayer] = $this->activeGame();

        GamePlayer::where('game_id', $game->id)
            ->where('user_id', $currentPlayer->id)
            ->update(['pawn_positions' => [2, -1, -1, -1]]);

        // Green entry is 13, so relative position 42 lands on global square 3.
        GamePlayer::where('game_id', $game->id)
            ->where('user_id', $otherPlayer->id)
            ->update(['pawn_positions' => [42, -1, -1, -1]]);

        $game->update([
            'dice_roll' => 1,
            'phase' => 'moving',
        ]);

        $this->fakeFirebase();
        $this->mock(ReleaseEscrow::class);

        $result = app(MovePawn::class)->execute($game, $currentPlayer, 0);

        $opponent = GamePlayer::where('game_id', $game->id)
            ->where('user_id', $otherPlayer->id)
            ->firstOrFail();

        $this->assertSame([-1, -1, -1, -1], $opponent->pawn_positions);
        $this->assertSame([$otherPlayer->id], $result['captured']);
        $this->assertSame($currentPlayer->id, $result['game']->current_turn_user_id);
    }

    private function activeGame(): array
    {
        $currentPlayer = User::factory()->create();
        $otherPlayer = User::factory()->create();

        $game = Game::factory()->create([
            'status' => 'active',
            'current_turn_user_id' => $currentPlayer->id,
            'turn_number' => 1,
            'phase' => 'rolling',
            'dice_roll' => null,
            'state_version' => 1,
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $currentPlayer->id,
            'color' => 'red',
            'pawn_positions' => [-1, -1, -1, -1],
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $otherPlayer->id,
            'color' => 'green',
            'pawn_positions' => [-1, -1, -1, -1],
        ]);

        return [$game, $currentPlayer, $otherPlayer];
    }

    private function fakeFirebase(): void
    {
        $this->mock(FirebaseService::class, function ($mock) {
            $mock->shouldReceive('updatePawnPosition')->zeroOrMoreTimes();
            $mock->shouldReceive('recordMove')->zeroOrMoreTimes();
            $mock->shouldReceive('advanceTurn')->zeroOrMoreTimes();
            $mock->shouldReceive('setWinner')->zeroOrMoreTimes();
        });
    }
}
