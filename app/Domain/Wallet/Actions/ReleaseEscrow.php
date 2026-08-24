<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Game\Exceptions\InvalidGameStateException;
use App\Domain\Wallet\DTOs\TransactionDTO;
use App\Models\Game;
use App\Models\GameEscrow;
use App\Models\Wallet;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ReleaseEscrow
{
    public function __construct(private readonly CreditWallet $creditWallet) {}

    /**
     * Release all held stakes for a completed game and credit the winner.
     *
     * A finished game is settled under a row lock and the payout reference is
     * deterministic, so the same game cannot create a second win transaction.
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

            $totalPot = $escrows->sum(
                fn (GameEscrow $escrow) => Money::toMinor($escrow->amount)
            );
            $platformFee = Money::toMinor($lockedGame->platform_fee);
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

            $this->creditWallet->execute(new TransactionDTO(
                wallet:          $winnerWallet,
                type:            'win',
                amount:          Money::fromMinor($payout),
                reference:       'win_game_' . $lockedGame->id,
                description:     "Winnings from game #{$lockedGame->id}",
            ));
        });
    }
}
