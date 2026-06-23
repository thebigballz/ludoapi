<?php

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Contracts\FraudRule;
use App\Domain\Fraud\DTOs\FraudSignal;
use App\Models\GamePlayer;
use App\Models\User;

class SuspiciousWinRateRule implements FraudRule
{
    private const MIN_GAMES = 20;
    private const WIN_RATE_THRESHOLD = 0.85;
    private const LOOKBACK = 40;

    public function evaluate(User $user): ?FraudSignal
    {
        $recent = GamePlayer::where('user_id', $user->id)
            ->whereIn('result', ['winner', 'loser'])
            ->latest('id')
            ->take(self::LOOKBACK)
            ->get();

        if ($recent->count() < self::MIN_GAMES) {
            return null;
        }

        $wins = $recent->where('result', 'winner')->count();
        $rate = $wins / $recent->count();

        if ($rate < self::WIN_RATE_THRESHOLD) {
            return null;
        }

        return new FraudSignal(
            rule: 'SuspiciousWinRateRule',
            severity: 'high',
            detail: sprintf('%d%% win rate over the last %d games (%d wins).', round($rate * 100), $recent->count(), $wins),
        );
    }
}