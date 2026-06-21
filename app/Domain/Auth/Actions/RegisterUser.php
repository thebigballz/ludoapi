<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DTOs\RegisterDTO;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterUser
{
    public function execute(RegisterDTO $dto): User
    {
        return DB::transaction(function () use ($dto) {
            $user = User::create([
                'name'     => $dto->name,
                'phone'    => $dto->phone,
                'email'    => $dto->email,
                'password' => Hash::make($dto->password),
            ]);

            // Create wallet immediately — every user must have one
            Wallet::create([
                'user_id' => $user->id,
                'balance' => 0.00,
            ]);

            return $user;
        });
    }
}