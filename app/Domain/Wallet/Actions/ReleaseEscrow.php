<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\DTOs\TransactionDTO;
use App\Domain\Game\Exceptions\InvalidGameStateException;
use App\Models\Game;
use App\Models\GameEscrow;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class ReleaseEscrow
{
    public function __construct(private readonly CreditWallet $creditWallet) {}

    /**
     * Release all held stakes for a completed game and credit the winner.
     *
     * This action is deliberately idempotent at the game level: callers must
     * settle an active game while holding its row lock. A finished game must
     * never release escrow a second time.
     *
     * @throws InvalidGameStateException
     */
    public function execute(Game $game, Wallet $winnerWallet): void
    {
        DB::transaction(function () use ($game, $winnerWallet) {
            $lockedGame = Game::whereKey($game->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedGame->status !== 'finished') {
                throw new InvalidGameStateException('Game must be finished before escrow can be released.');
            }

            $escrows = GameEscrow::where('game_id', $lockedGame->id)
                ->where('status', 'held')
                ->lockForUpdate()
                ->get();

            if ($escrows->isEmpty()) {
                throw new InvalidGameStateException('No held escrow remains for this game.');
            }

            $totalPot = $escrows->sum('amount');
            $platformFee = $lockedGame->platform_fee;
            $payout = $totalPot - $platformFee;

            if ($payout < 0) {
                throw new InvalidGameStateException('Platform fee cannot exceed the game pot.');
            }

            $updated = GameEscrow::where('game_id', $lockedGame->id)
                ->where('status', 'held')
                ->update(['status' => 'released']);

            if ($updated !== $escrows->count()) {
                throw new InvalidGameStateException('Escrow state changed while settling the game.');
            }

            // Deterministic reference prevents a second payout for the same game.
            $this->creditWallet->execute(new TransactionDTO(
                wallet:          $winnerWallet,
                type:            'win',
                amount:          $payout,
                reference:       'win_game_' . $lockedGame->id,
                description:     "Winnings from game #{$lockedGame->id}",
            ));
        });
    }
}
