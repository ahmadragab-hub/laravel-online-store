<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_cart_with_products(): void
    {
        $user = User::factory()->create();

        $cart = new Cart();
        $cart->user_id = $user->id;
        $cart->save();

        $product = Product::factory()->create();

        $cartItem = $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('cart.id', $cart->id)
            ->assertJsonPath('cart.items.0.id', $cartItem->id)
            ->assertJsonPath('cart.items.0.product_id', $product->id)
            ->assertJsonPath('cart.items.0.quantity', 2)
            ->assertJsonPath('cart.items.0.product.id', $product->id);
    }

    public function test_authenticated_user_can_remove_item_from_own_cart(): void
    {
        $user = User::factory()->create();

        $cart = new Cart();
        $cart->user_id = $user->id;
        $cart->save();

        $product = Product::factory()->create();

        $cartItem = $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/cart/items/{$cartItem->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Cart item removed.');

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItem->id,
        ]);
    }

    public function test_user_cannot_remove_another_users_cart_item(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $cart = new Cart();
        $cart->user_id = $owner->id;
        $cart->save();

        $product = Product::factory()->create();

        $cartItem = $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($otherUser);

        $this->deleteJson("/api/v1/cart/items/{$cartItem->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
        ]);
    }
}