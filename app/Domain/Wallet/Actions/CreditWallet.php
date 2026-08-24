<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\DTOs\TransactionDTO;
use App\Domain\Wallet\Exceptions\DuplicateTransactionException;
use App\Models\WalletTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class CreditWallet
{
    /**
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

            $balanceBefore = Money::toMinor($wallet->balance);
            $amount = Money::toMinor($dto->amount);
            $balanceAfter = $balanceBefore + $amount;

            $transaction = WalletTransaction::create([
                'wallet_id'            => $wallet->id,
                'user_id'              => $wallet->user_id,
                'type'                 => $dto->type,
                'status'               => 'completed',
                'amount'               => Money::fromMinor($amount),
                'balance_before'       => Money::fromMinor($balanceBefore),
                'balance_after'        => Money::fromMinor($balanceAfter),
                'reference'            => $dto->reference,
                'description'          => $dto->description,
                'transactionable_type' => $dto->transactionable ? get_class($dto->transactionable) : null,
                'transactionable_id'   => $dto->transactionable?->id,
            ]);

            $wallet->update([
                'balance'         => Money::fromMinor($balanceAfter),
                'total_deposited' => $dto->type === 'deposit'
                    ? Money::fromMinor(Money::toMinor($wallet->total_deposited) + $amount)
                    : $wallet->total_deposited,
                'total_won'       => $dto->type === 'win'
                    ? Money::fromMinor(Money::toMinor($wallet->total_won) + $amount)
                    : $wallet->total_won,
            ]);

            return $transaction;
        });
    }
}
