<?php

namespace Tests\Feature\Wallet;

use App\Domain\Game\Exceptions\InvalidGameStateException;
use App\Domain\Wallet\Actions\ReleaseEscrow;
use App\Models\Game;
use App\Models\GameEscrow;
use App\Models\GamePlayer;
use App\Models\Wallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_releases_escrow_and_credits_the_winner_once(): void
    {
        [$game, $winnerWallet] = $this->createFinishedGame(20.00, 2.00);

        GameEscrow::create([
            'wallet_id' => Wallet::where('user_id', $game->players()->first()->user_id)->value('id'),
            'game_id'   => $game->id,
            'amount'    => '10.00',
            'status'    => 'held',
        ]);

        GameEscrow::create([
            'wallet_id' => Wallet::where('user_id', $game->players()->skip(1)->first()->user_id)->value('id'),
            'game_id'   => $game->id,
            'amount'    => '10.00',
            'status'    => 'held',
        ]);

        app(ReleaseEscrow::class)->execute($game, $winnerWallet);

        $winnerWallet->refresh();

        $this->assertSame('18.00', $winnerWallet->balance);
        $this->assertSame('18.00', $winnerWallet->total_won);
        $this->assertSame(2, GameEscrow::where('game_id', $game->id)->where('status', 'released')->count());
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $winnerWallet->id,
            'type' => 'win',
            'reference' => 'win_game_' . $game->id,
            'amount' => '18.00',
        ]);
    }

    public function test_it_rejects_a_second_settlement_without_changing_the_wallet(): void
    {
        [$game, $winnerWallet] = $this->createFinishedGame(10.00, 0.00);

        GameEscrow::create([
            'wallet_id' => $winnerWallet->id,
            'game_id'   => $game->id,
            'amount'    => '10.00',
            'status'    => 'held',
        ]);

        $action = app(ReleaseEscrow::class);
        $action->execute($game, $winnerWallet);
        $winnerWallet->refresh();

        $this->expectException(InvalidGameStateException::class);
        $action->execute($game, $winnerWallet);

        $winnerWallet->refresh();
        $this->assertSame('10.00', $winnerWallet->balance);
        $this->assertSame(1, $winnerWallet->transactions()->where('reference', 'win_game_' . $game->id)->count());
    }

    public function test_it_rejects_settlement_when_no_held_escrow_exists(): void
    {
        [$game, $winnerWallet] = $this->createFinishedGame(10.00, 0.00);

        $this->expectException(InvalidGameStateException::class);
        app(ReleaseEscrow::class)->execute($game, $winnerWallet);

        $this->assertSame('0.00', $winnerWallet->fresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_it_rolls_back_when_the_platform_fee_exceeds_the_pot(): void
    {
        [$game, $winnerWallet] = $this->createFinishedGame(10.00, 11.00);

        GameEscrow::create([
            'wallet_id' => $winnerWallet->id,
            'game_id'   => $game->id,
            'amount'    => '10.00',
            'status'    => 'held',
        ]);

        $this->expectException(InvalidGameStateException::class);
        app(ReleaseEscrow::class)->execute($game, $winnerWallet);

        $this->assertDatabaseHas('game_escrows', [
            'game_id' => $game->id,
            'status' => 'held',
        ]);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function createFinishedGame(float $stake, float $fee): array
    {
        $winner = User::factory()->create();
        $loser = User::factory()->create();

        $winnerWallet = Wallet::create(['user_id' => $winner->id]);
        Wallet::create(['user_id' => $loser->id]);

        $game = Game::create([
            'firebase_room_id' => fake()->unique()->uuid(),
            'status' => 'finished',
            'stake_amount' => $stake,
            'platform_fee' => $fee,
            'winner_id' => $winner->id,
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $winner->id,
            'color' => 'red',
            'result' => 'winner',
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $loser->id,
            'color' => 'blue',
            'result' => 'loser',
        ]);

        return [$game, $winnerWallet];
    }
}
