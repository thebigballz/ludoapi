<?php

namespace App\Domain\Mpesa\DTOs;

readonly class StkPushDTO
{
    public function __construct(
        public int    $userId,
        public string $phone,
        public float  $amount,
    ) {}

    public static function fromRequest(array $data, int $userId): self
    {
        return new self(
            userId: $userId,
            phone:  $data['phone'],
            amount: $data['amount'],
        );
    }
}