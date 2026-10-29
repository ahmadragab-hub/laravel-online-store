<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_checkout_a_valid_cart(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'price' => '10.000',
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $cart = $user->cart()->create();

        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        Sanctum::actingAs($user);

        $response = $this->withHeader(
            'Idempotency-Key',
            'checkout-test-001'
        )->postJson('/api/v1/checkout');

        $response->assertCreated()
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.subtotal', '20.000')
            ->assertJsonPath('order.total', '20.000')
            ->assertJsonPath('order.currency', 'KWD')
            ->assertJsonPath('order.items.0.product_id', $product->id)
            ->assertJsonPath('order.items.0.product_name', $product->name)
            ->assertJsonPath('order.items.0.quantity', 2)
            ->assertJsonPath('order.items.0.unit_price', '10.000');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'checkout_idempotency_key' => 'checkout-test-001',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $response->json('order.id'),
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => '10.000',
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $cart->id,
        ]);
    }

    public function test_checkout_rejects_an_empty_cart(): void
    {
        $user = User::factory()->create();

        $user->cart()->create();

        Sanctum::actingAs($user);

        $this->withHeader(
            'Idempotency-Key',
            'checkout-empty-001'
        )->postJson('/api/v1/checkout')
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_rejects_insufficient_stock_without_changing_inventory(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'price' => '10.000',
            'stock_quantity' => 1,
            'status' => 'active',
        ]);

        $cart = $user->cart()->create();

        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader(
            'Idempotency-Key',
            'checkout-stock-001'
        )->postJson('/api/v1/checkout')
            ->assertUnprocessable();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 1,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_repeating_checkout_with_the_same_key_does_not_create_a_duplicate_order(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'price' => '10.000',
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $cart = $user->cart()->create();

        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $firstResponse = $this->withHeader(
            'Idempotency-Key',
            'checkout-replay-001'
        )->postJson('/api/v1/checkout');

        $firstResponse->assertCreated();

        $orderId = $firstResponse->json('order.id');

        $secondResponse = $this->withHeader(
            'Idempotency-Key',
            'checkout-replay-001'
        )->postJson('/api/v1/checkout');

        $secondResponse->assertOk()
            ->assertJsonPath('order.id', $orderId);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 9,
        ]);
    }

    public function test_guest_cannot_checkout(): void
    {
        $this->withHeader(
            'Idempotency-Key',
            'checkout-guest-001'
        )->postJson('/api/v1/checkout')
            ->assertUnauthorized();

        $this->assertDatabaseCount('orders', 0);
    }
}