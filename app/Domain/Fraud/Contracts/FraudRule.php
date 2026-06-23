<?php

namespace App\Domain\Fraud\Contracts;

use App\Domain\Fraud\DTOs\FraudSignal;
use App\Models\User;

interface FraudRule
{
    /**
     * Evaluate this rule against the user. Return null if nothing
     * tripped, or a FraudSignal describing what did.
     */
    public function evaluate(User $user): ?FraudSignal;
}