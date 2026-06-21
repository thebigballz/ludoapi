<?php

namespace App\Domain\Auth\DTOs;

readonly class RegisterDTO
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $password,
        public ?string $email = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            phone: $data['phone'],
            password: $data['password'],
            email: $data['email'] ?? null,
        );
    }
}