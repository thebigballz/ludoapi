<?php

namespace App\Domain\Wallet\DTOs;

use App\Models\Wallet;

readonly class TransactionDTO
{
    public function __construct(
        public Wallet $wallet,
        public string $type,
        public float $amount,
        public string $reference,
        public ?string $description = null,
        public ?object $transactionable = null,
    ) {}
}