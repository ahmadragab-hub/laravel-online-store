<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use App\Services\CatalogCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_rejects_a_missing_idempotency_key(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

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

        $this->postJson('/api/v1/checkout')
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

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

    public function test_two_different_users_can_checkout_using_the_same_idempotency_key(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $product = Product::factory()->create([
            'price' => '10.000',
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $cartA = $userA->cart()->create();
        $cartB = $userB->cart()->create();

        $cartA->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $cartB->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        Sanctum::actingAs($userA);

        $responseA = $this->withHeader(
            'Idempotency-Key',
            'shared-key-001'
        )->postJson('/api/v1/checkout');

        $responseA->assertCreated();

        Sanctum::actingAs($userB);

        $responseB = $this->withHeader(
            'Idempotency-Key',
            'shared-key-001'
        )->postJson('/api/v1/checkout');

        $responseB->assertCreated();

        $this->assertNotSame(
            $responseA->json('order.id'),
            $responseB->json('order.id')
        );

        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('order_items', 2);

        $this->assertDatabaseHas('orders', [
            'id' => $responseA->json('order.id'),
            'user_id' => $userA->id,
            'checkout_idempotency_key' => 'shared-key-001',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $responseB->json('order.id'),
            'user_id' => $userB->id,
            'checkout_idempotency_key' => 'shared-key-001',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 6,
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $cartA->id,
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $cartB->id,
        ]);
    }

    public function test_checkout_rolls_back_all_database_changes_if_a_failure_occurs_midway(): void
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

        $this->mock(CatalogCacheService::class, function ($mock) {
            $mock->shouldReceive('invalidate')
                ->once()
                ->andThrow(
                    ValidationException::withMessages([
                        'checkout' => ['Simulated failure after checkout database operations.'],
                    ])
                );
        });

        Sanctum::actingAs($user);

        $this->withHeader(
            'Idempotency-Key',
            'checkout-rollback-001'
        )->postJson('/api/v1/checkout')
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_two_checkouts_cannot_oversell_limited_stock(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $product = Product::factory()->create([
            'price' => '10.000',
            'stock_quantity' => 3,
            'status' => 'active',
        ]);

        $cartA = $userA->cart()->create();
        $cartB = $userB->cart()->create();

        $cartA->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $cartB->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        Sanctum::actingAs($userA);

        $responseA = $this->withHeader(
            'Idempotency-Key',
            'checkout-limited-stock-A'
        )->postJson('/api/v1/checkout');

        $responseA->assertCreated();

        Sanctum::actingAs($userB);

        $responseB = $this->withHeader(
            'Idempotency-Key',
            'checkout-limited-stock-B'
        )->postJson('/api/v1/checkout');

        $responseB->assertUnprocessable();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 1,
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $cartA->id,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cartB->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_reusing_the_same_key_after_changing_the_cart_returns_the_original_order(): void
    {
        $user = User::factory()->create();

        $productA = Product::factory()->create([
            'price' => '10.000',
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $productB = Product::factory()->create([
            'price' => '20.000',
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $cart = $user->cart()->create();

        $cart->items()->create([
            'product_id' => $productA->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $firstResponse = $this->withHeader(
            'Idempotency-Key',
            'checkout-changed-cart-001'
        )->postJson('/api/v1/checkout');

        $firstResponse->assertCreated();

        $originalOrderId = $firstResponse->json('order.id');

        // Simulate the user adding a different product after checkout.
        $cart->items()->create([
            'product_id' => $productB->id,
            'quantity' => 2,
        ]);

        $secondResponse = $this->withHeader(
            'Idempotency-Key',
            'checkout-changed-cart-001'
        )->postJson('/api/v1/checkout');

        $secondResponse->assertOk()
            ->assertJsonPath('order.id', $originalOrderId);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);

        $this->assertDatabaseHas('products', [
            'id' => $productA->id,
            'stock_quantity' => 9,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $productB->id,
            'stock_quantity' => 10,
        ]);

        // The new cart item was not checked out by the replayed request.
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $productB->id,
            'quantity' => 2,
        ]);
    }

    public function test_checkout_rejects_a_product_that_became_inactive(): void
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

        // The product becomes inactive before checkout.
        $product->update([
            'status' => 'inactive',
        ]);

        Sanctum::actingAs($user);

        $this->withHeader(
            'Idempotency-Key',
            'checkout-inactive-001'
        )->postJson('/api/v1/checkout')
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_checkout_uses_the_current_product_price(): void
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

        // The product price changes after it was added to the cart.
        $product->update([
            'price' => '12.500',
        ]);

        Sanctum::actingAs($user);

        $response = $this->withHeader(
            'Idempotency-Key',
            'checkout-price-change-001'
        )->postJson('/api/v1/checkout');

        $response->assertCreated()
            ->assertJsonPath('order.subtotal', '25.000')
            ->assertJsonPath('order.total', '25.000')
            ->assertJsonPath('order.items.0.unit_price', '12.500');

        $this->assertDatabaseHas('order_items', [
            'order_id' => $response->json('order.id'),
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => '12.500',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);
    }
}
