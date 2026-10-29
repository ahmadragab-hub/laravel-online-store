<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_receive_an_api_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'password' => 'ExamplePass123!',
            'password_confirmation' => 'ExamplePass123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Registration successful.')
            ->assertJsonPath('user.name', 'Test Customer')
            ->assertJsonPath('user.email', 'customer@example.com')
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'role'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'customer@example.com',
            'role' => 'customer',
        ]);

        $user = User::where('email', 'customer@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('ExamplePass123!', $user->password));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'store-api',
        ]);
    }

    public function test_registration_rejects_an_existing_email(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
        ]);

        $response = $this->postJson('/api/v1/register', [
            'name' => 'Another Customer',
            'email' => 'customer@example.com',
            'password' => 'ExamplePass123!',
            'password_confirmation' => 'ExamplePass123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'password' => 'ExamplePass123!',
            'password_confirmation' => 'DifferentPass123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_public_registration_cannot_assign_the_admin_role(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'password' => 'ExamplePass123!',
            'password_confirmation' => 'ExamplePass123!',
            'role' => 'admin',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'customer');

        $this->assertDatabaseHas('users', [
            'email' => 'customer@example.com',
            'role' => 'customer',
        ]);
    }
}