<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\DTOs\TransactionDTO;
use App\Models\Game;
use App\Models\GameEscrow;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RefundEscrow
{
    public function __construct(private readonly CreditWallet $creditWallet) {}

    public function execute(Game $game): void
    {
        DB::transaction(function () use ($game) {
            $escrows = GameEscrow::where('game_id', $game->id)
                ->where('status', 'held')
                ->with('wallet')
                ->get();

            foreach ($escrows as $escrow) {
                $this->creditWallet->execute(new TransactionDTO(
                    wallet:      $escrow->wallet,
                    type:        'refund',
                    amount:      $escrow->amount,
                    reference:   'refund_escrow_' . $escrow->id . '_' . Str::random(8),
                    description: "Stake refunded for cancelled game #{$game->id}",
                ));

                $escrow->update(['status' => 'refunded']);
            }
        });
    }
}