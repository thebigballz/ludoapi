<?php

namespace App\Domain\Auth\DTOs;

readonly class LoginDTO
{
    public function __construct(
        public string $phone,
        public string $password,
        public string $device_name,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            phone: $data['phone'],
            password: $data['password'],
            device_name: $data['device_name'] ?? 'mobile',
        );
    }
}