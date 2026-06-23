<?php

namespace App\Jobs;

use App\Domain\Fraud\FraudChecker;
use App\Models\FraudFlag;
use App\Models\User;
use App\Notifications\AccountFlagged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FlagSuspiciousAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $userId) {}

    public function handle(FraudChecker $fraudChecker): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        foreach ($fraudChecker->check($user) as $signal) {
            // Don't re-flag the same open issue every time another
            // action re-triggers a check.
            $alreadyOpen = FraudFlag::where('user_id', $user->id)
                ->where('rule', $signal->rule)
                ->where('status', 'open')
                ->exists();

            if ($alreadyOpen) {
                continue;
            }

            $flag = FraudFlag::create([
                'user_id'  => $user->id,
                'rule'     => $signal->rule,
                'severity' => $signal->severity,
                'detail'   => $signal->detail,
                'status'   => 'open',
            ]);

            $user->notify(new AccountFlagged($flag));
        }
    }
}