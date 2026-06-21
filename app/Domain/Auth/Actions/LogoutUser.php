<?php

namespace App\Domain\Auth\Actions;

use App\Models\User;

class LogoutUser
{
    public function execute(User $user): void
    {
        // Revoke only the current token, not all devices
        $user->currentAccessToken()->delete();
    }
}