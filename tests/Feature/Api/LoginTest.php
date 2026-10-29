<?php
namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_a_token(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'ExamplePass123!',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'customer@example.com',
            'password' => 'ExamplePass123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'ExamplePass123!',
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'customer@example.com',
            'password' => 'WrongPass123!',
        ])->assertUnauthorized();
    }
}