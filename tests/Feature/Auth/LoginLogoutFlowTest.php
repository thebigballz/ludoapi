<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginLogoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_token_can_authenticate_logout(): void
    {
        User::factory()->create([
            'phone' => '254712345678',
            'password' => Hash::make('password123'),
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'phone' => '254712345678',
            'password' => 'password123',
            'device_name' => 'postman',
        ])->assertOk();

        $token = $login->json('token');
        $this->assertNotEmpty($token);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out successfully.']);
    }
}
