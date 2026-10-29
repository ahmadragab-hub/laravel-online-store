<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_add_product_to_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'status' => 'active',
            'stock_quantity' => 10,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('item.product_id', $product->id)
            ->assertJsonPath('item.quantity', 2);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_adding_same_product_increases_existing_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'status' => 'active',
            'stock_quantity' => 10,
        ]);

        $this->actingAs($user, 'sanctum');

        $payload = ['product_id' => $product->id, 'quantity' => 2];

        $this->postJson('/api/v1/cart/items', $payload)->assertCreated();
        $this->postJson('/api/v1/cart/items', $payload)->assertCreated();

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 4,
        ]);

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_guest_cannot_add_product_to_cart(): void
    {
        $product = Product::factory()->create(['status' => 'active']);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertUnauthorized();
    }

    public function test_cart_rejects_quantity_above_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'status' => 'active',
            'stock_quantity' => 2,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 3,
            ])
            ->assertUnprocessable();
    }
}
