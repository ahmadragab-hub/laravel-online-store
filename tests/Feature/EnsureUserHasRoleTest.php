<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnsureUserHasRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'role:admin'])
            ->get('/test/admin-only', fn () => response()->json(['ok' => true]));
    }

    public function test_customer_is_forbidden_from_admin_route(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        Sanctum::actingAs($customer);

        $this->getJson('/test/admin-only')->assertForbidden();
    }

    public function test_guest_is_rejected_before_role_check(): void
    {
        $this->getJson('/test/admin-only')->assertUnauthorized();
    }

    public function test_admin_can_access_admin_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $this->getJson('/test/admin-only')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }
}
