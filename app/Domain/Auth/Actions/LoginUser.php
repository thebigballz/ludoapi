<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DTOs\LoginDTO;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class LoginUser
{
    /**
     * @throws AuthenticationException
     */
    public function execute(LoginDTO $dto): array
    {
        $user = User::where('phone', $dto->phone)->first();

        if (! $user || ! Hash::check($dto->password, $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        if ($user->is_banned) {
            throw new AuthenticationException('Your account has been suspended.');
        }

        // Revoke previous tokens for this device to avoid token bloat
        $user->tokens()->where('name', $dto->device_name)->delete();

        $token = $user->createToken($dto->device_name)->plainTextToken;

        return [
            'user'  => $user,
            'token' => $token,
        ];
    }
}