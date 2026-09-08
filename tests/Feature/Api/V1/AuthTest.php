<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_username(): void
    {
        User::factory()->create([
            'username' => 'operador',
            'email' => 'operador@example.test',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'operador',
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('codigo', 200)
            ->assertJsonPath('datos.token_type', 'Bearer')
            ->assertJsonPath('datos.user.username', 'operador');
    }

    public function test_invalid_credentials_are_generic(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'not-found',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('mensaje', 'Las credenciales no son válidas.');
    }

    public function test_token_can_read_me_and_logout(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('angular')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('datos.id', $user->id);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        app('auth')->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
