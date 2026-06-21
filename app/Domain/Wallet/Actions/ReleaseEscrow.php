<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\DTOs\TransactionDTO;
use App\Models\Game;
use App\Models\GameEscrow;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReleaseEscrow
{
    public function __construct(private readonly CreditWallet $creditWallet) {}

    public function execute(Game $game, Wallet $winnerWallet): void
    {
        DB::transaction(function () use ($game, $winnerWallet) {
            $escrows = GameEscrow::where('game_id', $game->id)
                ->where('status', 'held')
                ->get();

            $totalPot    = $escrows->sum('amount');
            $platformFee = $game->platform_fee;
            $payout      = $totalPot - $platformFee;

            // Mark all escrows released
            GameEscrow::where('game_id', $game->id)
                ->where('status', 'held')
                ->update(['status' => 'released']);

            // Credit winner
            $this->creditWallet->execute(new TransactionDTO(
                wallet:      $winnerWallet,
                type:        'win',
                amount:      $payout,
                reference:   'win_game_' . $game->id . '_' . Str::random(8),
                description: "Winnings from game #{$game->id}",
            ));
        });
    }
}