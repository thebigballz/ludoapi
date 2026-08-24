<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\DTOs\TransactionDTO;
use App\Domain\Wallet\Exceptions\DuplicateTransactionException;
use App\Domain\Wallet\Exceptions\InsufficientBalanceException;
use App\Models\WalletTransaction;
use App\Support\Money;
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

            $balanceBefore = Money::toMinor($wallet->balance);
            $amount = Money::toMinor($dto->amount);

            if ($balanceBefore < $amount) {
                throw new InsufficientBalanceException();
            }

            $balanceAfter = $balanceBefore - $amount;

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
                'total_withdrawn' => $dto->type === 'withdrawal'
                    ? Money::fromMinor(Money::toMinor($wallet->total_withdrawn) + $amount)
                    : $wallet->total_withdrawn,
                'total_lost'      => $dto->type === 'stake'
                    ? Money::fromMinor(Money::toMinor($wallet->total_lost) + $amount)
                    : $wallet->total_lost,
            ]);

            return $transaction;
        });
    }
}
