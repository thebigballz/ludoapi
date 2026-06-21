<?php

namespace App\Domain\Game\DTOs;

readonly class CreateTableDTO
{
    public function __construct(
        public int $userId,
        public float $stakeAmount,
    ) {}

    public static function fromRequest(array $data, int $userId): self
    {
        return new self(
            userId:      $userId,
            stakeAmount: $data['stake_amount'],
        );
    }
}