<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;


class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_list_their_orders(): void
    {
        $user = User::factory()->create();

        Order::factory()->count(2)->create([
            'user_id' => $user->id,
        ]);

        Order::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_customer_can_view_their_order_with_items(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Keyboard',
            'quantity' => 2,
            'unit_price' => '10.000',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.items.0.product_name', 'Keyboard')
            ->assertJsonPath('data.items.0.quantity', 2);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertNotFound();
    }

    public function test_guest_cannot_list_orders(): void
    {
        $this->getJson('/api/v1/orders')
            ->assertUnauthorized();
    }

    public function test_guest_cannot_view_an_order(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertUnauthorized();
    }
}
