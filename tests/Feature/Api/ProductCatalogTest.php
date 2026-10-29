<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_active_products(): void
    {
        $category = Category::factory()->create();

        $activeProduct = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'inactive',
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeProduct->id);
    }

    public function test_it_includes_the_product_category(): void
    {
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.category.id', $category->id);
    }

    public function test_it_respects_the_per_page_parameter(): void
    {
        $category = Category::factory()->create();

        Product::factory()->count(5)->create([
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/products?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_it_filters_products_by_search_term(): void
    {
        $category = Category::factory()->create();

        $matchingProduct = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Mechanical Keyboard',
            'status' => 'active',
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Wireless Mouse',
            'status' => 'active',
        ]);

        $response = $this->getJson(
            '/api/v1/products?search=Keyboard'
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingProduct->id);
    }

    public function test_it_filters_products_by_category(): void
    {
        $firstCategory = Category::factory()->create();
        $secondCategory = Category::factory()->create();

        $matchingProduct = Product::factory()->create([
            'category_id' => $firstCategory->id,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'category_id' => $secondCategory->id,
            'status' => 'active',
        ]);

        $response = $this->getJson(
            '/api/v1/products?category_id='.$firstCategory->id
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingProduct->id);
    }

    public function test_it_rejects_invalid_catalog_parameters(): void
    {
        $response = $this->getJson(
            '/api/v1/products?per_page=100'
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_an_inactive_product_cannot_be_shown(): void
    {
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'inactive',
        ]);

        $response = $this->getJson(
            '/api/v1/products/'.$product->slug
        );

        $response->assertNotFound();
    }
    
}