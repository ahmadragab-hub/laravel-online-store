<?php

namespace Tests\Feature\Api\Admin;

use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_adjust_stock_and_audit_record_is_created(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create(['stock_quantity' => 10]);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/v1/admin/products/{$product->id}/inventory-adjustments",
            [
                'quantity_change' => 5,
                'reason' => 'Received new stock',
            ]
        )
            ->assertOk()
            ->assertJsonPath('product.stock_quantity', 15)
            ->assertJsonPath('adjustment.quantity_before', 10)
            ->assertJsonPath('adjustment.quantity_change', 5)
            ->assertJsonPath('adjustment.quantity_after', 15);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 15,
        ]);

        $this->assertDatabaseHas('inventory_adjustments', [
            'product_id' => $product->id,
            'user_id' => $admin->id,
            'quantity_before' => 10,
            'quantity_change' => 5,
            'quantity_after' => 15,
            'reason' => 'Received new stock',
        ]);
    }

    public function test_adjustment_cannot_make_stock_negative(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create(['stock_quantity' => 3]);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/v1/admin/products/{$product->id}/inventory-adjustments",
            [
                'quantity_change' => -5,
                'reason' => 'Incorrect stock count',
            ]
        )->assertUnprocessable();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 3,
        ]);

        $this->assertDatabaseCount('inventory_adjustments', 0);
    }

    public function test_customer_cannot_adjust_inventory(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $product = Product::factory()->create(['stock_quantity' => 10]);

        Sanctum::actingAs($customer);

        $this->postJson(
            "/api/v1/admin/products/{$product->id}/inventory-adjustments",
            [
                'quantity_change' => 5,
                'reason' => 'Received new stock',
            ]
        )->assertForbidden();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);

        $this->assertDatabaseCount('inventory_adjustments', 0);
    }

    public function test_guest_cannot_adjust_inventory(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $this->postJson(
            "/api/v1/admin/products/{$product->id}/inventory-adjustments",
            [
                'quantity_change' => 5,
                'reason' => 'Received new stock',
            ]
        )->assertUnauthorized();
    }

    public function test_zero_adjustment_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create(['stock_quantity' => 10]);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/v1/admin/products/{$product->id}/inventory-adjustments",
            [
                'quantity_change' => 0,
                'reason' => 'No change',
            ]
        )->assertUnprocessable();

        $this->assertDatabaseCount('inventory_adjustments', 0);
    }
}
