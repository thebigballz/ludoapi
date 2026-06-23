<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Contracts\FraudRule;
use App\Domain\Fraud\DTOs\FraudSignal;
use App\Models\MpesaTransaction;
use App\Models\User;

class DailyWithdrawalLimitRule implements FraudRule
{
    public function evaluate(User $user): ?FraudSignal
    {
        $limit = (float) config('ludo.max_daily_withdrawal');

        $query = MpesaTransaction::where('user_id', $user->id)
            ->where('type', 'b2c')
            ->whereIn('status', ['pending', 'success'])
            ->where('created_at', '>=', now()->subDay());

        $total = $query->sum('amount');

        if ($total < $limit) {
            return null;
        }

        return new FraudSignal(
            rule: 'DailyWithdrawalLimitRule',
            severity: 'high',
            detail: sprintf(
                '%d withdrawal(s) totalling KES %s in the last 24 hours — at or above the KES %s daily limit.',
                $query->count(),
                number_format($total, 2),
                number_format($limit, 2),
            ),
        );
    }
}
