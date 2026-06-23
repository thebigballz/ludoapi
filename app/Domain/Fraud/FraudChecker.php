<?php

namespace App\Domain\Fraud;

use App\Domain\Fraud\Contracts\FraudRule;
use App\Domain\Fraud\Rules\DailyWithdrawalLimitRule;
use App\Domain\Fraud\Rules\PhoneMatchesRegistrationRule;
use App\Domain\Fraud\Rules\RapidDepositRule;
use App\Domain\Fraud\Rules\SuspiciousWinRateRule;
use App\Models\User;
use Illuminate\Support\Collection;

class FraudChecker
{
    /** @var FraudRule[] */
    private array $rules;

    public function __construct(?array $rules = null)
    {
        $this->rules = $rules ?? [
            app(DailyWithdrawalLimitRule::class),
            app(PhoneMatchesRegistrationRule::class),
            app(RapidDepositRule::class),
            app(SuspiciousWinRateRule::class),
        ];
    }

    /**
     * @return Collection<int, \App\Domain\Fraud\DTOs\FraudSignal>
     */
    public function check(User $user): Collection
    {
        return collect($this->rules)
            ->map(fn (FraudRule $rule) => $rule->evaluate($user))
            ->filter()
            ->values();
    }
}
