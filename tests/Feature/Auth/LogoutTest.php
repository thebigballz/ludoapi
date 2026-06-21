<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out successfully.']);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_logout_without_token_returns_json_401_not_login_redirect(): void
    {
        $this->post('/api/v1/auth/logout')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }
}
