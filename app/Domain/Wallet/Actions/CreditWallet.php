<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\DTOs\TransactionDTO;
use App\Domain\Wallet\Exceptions\DuplicateTransactionException;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class CreditWallet
{
    /**
     * @throws DuplicateTransactionException
     */
    public function execute(TransactionDTO $dto): WalletTransaction
    {
        // Block duplicate references before opening the transaction
        if (WalletTransaction::where('reference', $dto->reference)->exists()) {
            throw new DuplicateTransactionException();
        }

        return DB::transaction(function () use ($dto) {
            // Lock the wallet row for this transaction
            $wallet = $dto->wallet->lockForUpdate()->first()
                ?? $dto->wallet->refresh();

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter  = $balanceBefore + (float) $dto->amount;

            $transaction = WalletTransaction::create([
                'wallet_id'            => $wallet->id,
                'user_id'              => $wallet->user_id,
                'type'                 => $dto->type,
                'status'               => 'completed',
                'amount'               => $dto->amount,
                'balance_before'       => $balanceBefore,
                'balance_after'        => $balanceAfter,
                'reference'            => $dto->reference,
                'description'          => $dto->description,
                'transactionable_type' => $dto->transactionable ? get_class($dto->transactionable) : null,
                'transactionable_id'   => $dto->transactionable?->id,
            ]);

            // Update wallet balance and running total
            $wallet->update([
                'balance'         => $balanceAfter,
                'total_deposited' => $dto->type === 'deposit'
                    ? $wallet->total_deposited + $dto->amount
                    : $wallet->total_deposited,
                'total_won'       => $dto->type === 'win'
                    ? $wallet->total_won + $dto->amount
                    : $wallet->total_won,
            ]);

            return $transaction;
        });
    }
}