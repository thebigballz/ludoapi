<?php

namespace Tests\Feature\Wallet;

use App\Domain\Game\Exceptions\InvalidGameStateException;
use App\Domain\Wallet\Actions\RefundEscrow;
use App\Models\Game;
use App\Models\GameEscrow;
use App\Models\Wallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscrowRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refunds_each_held_escrow_and_marks_it_refunded(): void
    {
        [$game, $wallets] = $this->createCancelledGame();

        foreach ($wallets as $wallet) {
            GameEscrow::create([
                'wallet_id' => $wallet->id,
                'game_id' => $game->id,
                'amount' => '10.00',
                'status' => 'held',
            ]);
        }

        app(RefundEscrow::class)->execute($game);

        foreach ($wallets as $wallet) {
            $this->assertSame('10.00', $wallet->fresh()->balance);
        }

        $this->assertSame(2, GameEscrow::where('game_id', $game->id)->where('status', 'refunded')->count());
        $this->assertSame(2, $game->fresh()->escrows()->count());
        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_it_rejects_a_second_refund_without_creating_another_transaction(): void
    {
        [$game, $wallets] = $this->createCancelledGame();

        GameEscrow::create([
            'wallet_id' => $wallets[0]->id,
            'game_id' => $game->id,
            'amount' => '10.00',
            'status' => 'held',
        ]);

        $action = app(RefundEscrow::class);
        $action->execute($game);

        $this->expectException(InvalidGameStateException::class);
        $action->execute($game);

        $this->assertSame('10.00', $wallets[0]->fresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    private function createCancelledGame(): array
    {
        $users = [User::factory()->create(), User::factory()->create()];
        $wallets = array_map(
            fn (User $user) => Wallet::create(['user_id' => $user->id]),
            $users
        );

        $game = Game::create([
            'firebase_room_id' => fake()->unique()->uuid(),
            'status' => 'cancelled',
            'stake_amount' => '10.00',
            'platform_fee' => '0.00',
        ]);

        return [$game, $wallets];
    }
}
