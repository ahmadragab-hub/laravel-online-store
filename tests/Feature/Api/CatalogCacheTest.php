<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_listing_serves_cached_payload_until_catalog_is_invalidated(): void
    {
        Cache::flush();

        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Cached Keyboard',
            'status' => 'active',
        ]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Cached Keyboard');

        $product->update(['name' => 'Updated Keyboard']);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Cached Keyboard');

        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/products/{$product->id}", [
            'name' => 'Updated Keyboard',
        ])->assertOk();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Updated Keyboard');
    }

    public function test_checkout_invalidates_cached_stock_levels(): void
    {
        Cache::flush();

        $customer = User::factory()->create();

        $product = Product::factory()->create([
            'price' => '10.000',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.stock_quantity', 5);

        $cart = $customer->cart()->create();

        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        Sanctum::actingAs($customer);

        $this->withHeader('Idempotency-Key', 'checkout-cache-001')
            ->postJson('/api/v1/checkout')
            ->assertCreated();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.stock_quantity', 3);
    }
}
