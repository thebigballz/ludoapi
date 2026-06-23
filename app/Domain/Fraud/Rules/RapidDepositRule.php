<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Contracts\FraudRule;
use App\Domain\Fraud\DTOs\FraudSignal;
use App\Models\MpesaTransaction;
use App\Models\User;

class RapidDepositRule implements FraudRule
{
    private const WINDOW_MINUTES = 10;
    private const THRESHOLD = 5;

    public function evaluate(User $user): ?FraudSignal
    {
        $count = MpesaTransaction::where('user_id', $user->id)
            ->where('type', 'stk_push')
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subMinutes(self::WINDOW_MINUTES))
            ->count();

        if ($count < self::THRESHOLD) {
            return null;
        }

        return new FraudSignal(
            rule: 'RapidDepositRule',
            severity: 'low',
            detail: "{$count} successful deposits within " . self::WINDOW_MINUTES . ' minutes.',
        );
    }
}
