<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Game\Exceptions\InvalidGameStateException;
use App\Domain\Wallet\DTOs\TransactionDTO;
use App\Models\Game;
use App\Models\GameEscrow;
use Illuminate\Support\Facades\DB;

class RefundEscrow
{
    public function __construct(private readonly CreditWallet $creditWallet) {}

    /**
     * Refund all held stakes for a cancelled game exactly once.
     *
     * @throws InvalidGameStateException
     */
    public function execute(Game $game): void
    {
        DB::transaction(function () use ($game) {
            $lockedGame = Game::whereKey($game->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedGame->status !== 'cancelled') {
                throw new InvalidGameStateException('Game must be cancelled before escrow can be refunded.');
            }

            $escrows = GameEscrow::where('game_id', $lockedGame->id)
                ->where('status', 'held')
                ->with('wallet')
                ->lockForUpdate()
                ->get();

            foreach ($escrows as $escrow) {
                $this->creditWallet->execute(new TransactionDTO(
                    wallet:      $escrow->wallet,
                    type:        'refund',
                    amount:      $escrow->amount,
                    reference:   'refund_escrow_' . $escrow->id,
                    description: "Stake refunded for cancelled game #{$lockedGame->id}",
                ));

                $escrow->update(['status' => 'refunded']);
            }
        });
    }
}
