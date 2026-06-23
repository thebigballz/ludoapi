<?php

namespace App\Domain\Fraud\DTOs;

readonly class FraudSignal
{
    public function __construct(
        public string $rule,
        public string $severity, // low | medium | high
        public string $detail,
    ) {}
}