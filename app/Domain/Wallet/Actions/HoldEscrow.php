<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\DTOs\TransactionDTO;
use App\Domain\Wallet\Exceptions\InsufficientBalanceException;
use App\Models\Game;
use App\Models\GameEscrow;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HoldEscrow
{
    public function __construct(private readonly DebitWallet $debitWallet) {}

    /**
     * @throws InsufficientBalanceException
     */
    public function execute(Wallet $wallet, Game $game): GameEscrow
    {
        return DB::transaction(function () use ($wallet, $game) {
            $escrow = GameEscrow::create([
                'wallet_id' => $wallet->id,
                'game_id'   => $game->id,
                'amount'    => $game->stake_amount,
                'status'    => 'held',
            ]);

            $this->debitWallet->execute(new TransactionDTO(
                wallet:          $wallet,
                type:            'stake',
                amount:          $game->stake_amount,
                reference:       'escrow_' . $escrow->id . '_' . Str::random(8),
                description:     "Stake held for game #{$game->id}",
                transactionable: $escrow,
            ));

            return $escrow;
        });
    }
}