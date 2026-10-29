<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();

        Sanctum::actingAs($this->admin);
    }

    public function test_admin_cancelling_pending_order_restores_stock_once(): void
    {
        [$order, $product] = $this->makeReservedOrder();

        $this->patchJson(
            "/api/v1/admin/orders/{$order->id}/status",
            ['status' => 'cancelled']
        )->assertOk()
            ->assertJsonPath('order.status', 'cancelled')
            ->assertJsonPath(
                'order.inventory_reservation_status',
                'released'
            );

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);

        // A second cancellation must not restore stock again.
        $this->patchJson(
            "/api/v1/admin/orders/{$order->id}/status",
            ['status' => 'cancelled']
        )->assertUnprocessable();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);
    }


    public function test_admin_cannot_manually_move_pending_order_to_processing(): void
    {
        [$order, $product] = $this->makeReservedOrder();

        $this->patchJson(
            "/api/v1/admin/orders/{$order->id}/status",
            ['status' => 'processing']
        )->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
            'inventory_reservation_status' => 'reserved',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);
    }

    public function test_processing_order_can_be_completed(): void
    {
        [$order, $product] = $this->makeReservedOrder();

        $order->update([
            'status' => 'processing',
            'inventory_reservation_status' => 'consumed',
        ]);

        $this->patchJson(
            "/api/v1/admin/orders/{$order->id}/status",
            ['status' => 'completed']
        )->assertOk()
            ->assertJsonPath('order.status', 'completed');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);
    }

    public function test_pending_order_cannot_skip_directly_to_completed(): void
    {
        [$order, $product] = $this->makeReservedOrder();

        $this->patchJson(
            "/api/v1/admin/orders/{$order->id}/status",
            ['status' => 'completed']
        )->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
            'inventory_reservation_status' => 'reserved',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);
    }

    public function test_expired_reservation_restores_stock(): void
    {
        [$order, $product] = $this->makeReservedOrder();

        $order->update([
            'reservation_expires_at' => now()->subMinute(),
        ]);

        $this->artisan('orders:expire-reservations')
            ->assertExitCode(0);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'expired',
            'inventory_reservation_status' => 'released',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);

        // Running expiry again must not release the same stock twice.
        $this->artisan('orders:expire-reservations')
            ->assertExitCode(0);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);
    }


    public function test_admin_cannot_manually_process_an_expired_order(): void
    {
        [$order, $product] = $this->makeReservedOrder();

        $order->update([
            'reservation_expires_at' => now()->subMinute(),
        ]);

        $this->patchJson(
            "/api/v1/admin/orders/{$order->id}/status",
            ['status' => 'processing']
        )->assertUnprocessable();

        // The admin request is rejected before the lifecycle service runs.
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
            'inventory_reservation_status' => 'reserved',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);

        // The scheduled expiry workflow handles the overdue reservation.
        $this->artisan('orders:expire-reservations')
            ->assertExitCode(0);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'expired',
            'inventory_reservation_status' => 'released',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);
    }


    private function makeReservedOrder(): array
    {
        $customer = User::factory()->create();

        // Represents stock after checkout has reserved two units.
        $product = Product::factory()->create([
            'stock_quantity' => 8,
        ]);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => 'pending',
            'inventory_reservation_status' => 'reserved',
            'reservation_expires_at' => now()->addMinutes(15),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => $product->getRawOriginal('price'),
        ]);

        return [$order, $product];
    }
}
