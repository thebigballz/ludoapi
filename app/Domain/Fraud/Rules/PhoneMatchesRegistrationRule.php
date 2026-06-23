<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Contracts\FraudRule;
use App\Domain\Fraud\DTOs\FraudSignal;
use App\Models\MpesaTransaction;
use App\Models\User;

class PhoneMatchesRegistrationRule implements FraudRule
{
    public function evaluate(User $user): ?FraudSignal
    {
        $latest = MpesaTransaction::where('user_id', $user->id)
            ->where('type', 'stk_push')
            ->where('status', 'success')
            ->latest()
            ->first();

        if (! $latest || ! $latest->phone) {
            return null;
        }

        if ($this->normalize($latest->phone) === $this->normalize($user->phone)) {
            return null;
        }

        return new FraudSignal(
            rule: 'PhoneMatchesRegistrationRule',
            severity: 'medium',
            detail: "Latest deposit was paid from {$latest->phone}, which does not match the registered account phone {$user->phone}.",
        );
    }

    private function normalize(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }
}
