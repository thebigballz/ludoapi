<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_in_response(): void
    {
        User::factory()->create([
            'phone' => '254712345678',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '254712345678',
            'password' => 'password123',
            'device_name' => 'postman',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'phone'],
            ]);

        $this->assertNotEmpty($response->json('token'));
    }
}
