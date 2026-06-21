<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\DTOs\TransactionDTO;
use App\Domain\Wallet\Exceptions\DuplicateTransactionException;
use App\Domain\Wallet\Exceptions\InsufficientBalanceException;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class DebitWallet
{
    /**
     * @throws InsufficientBalanceException
     * @throws DuplicateTransactionException
     */
    public function execute(TransactionDTO $dto): WalletTransaction
    {
        if (WalletTransaction::where('reference', $dto->reference)->exists()) {
            throw new DuplicateTransactionException();
        }

        return DB::transaction(function () use ($dto) {
            $wallet = $dto->wallet->lockForUpdate()->first()
                ?? $dto->wallet->refresh();

            $balanceBefore = (float) $wallet->balance;

            if ($balanceBefore < (float) $dto->amount) {
                throw new InsufficientBalanceException();
            }

            $balanceAfter = $balanceBefore - (float) $dto->amount;

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

            $wallet->update([
                'balance'          => $balanceAfter,
                'total_withdrawn'  => $dto->type === 'withdrawal'
                    ? $wallet->total_withdrawn + $dto->amount
                    : $wallet->total_withdrawn,
                'total_lost'       => $dto->type === 'stake'
                    ? $wallet->total_lost + $dto->amount
                    : $wallet->total_lost,
            ]);

            return $transaction;
        });
    }
}