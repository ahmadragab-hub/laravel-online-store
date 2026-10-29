<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_categories_with_expected_fields(): void
    {
        $category = Category::factory()->create([
            'name' => 'Laptops',
            'slug' => 'laptops',
            'description' => 'Portable computers',
        ]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $category->id)
            ->assertJsonPath('data.0.name', 'Laptops')
            ->assertJsonPath('data.0.slug', 'laptops')
            ->assertJsonPath('data.0.description', 'Portable computers');
    }

    public function test_it_paginates_categories(): void
    {
        Category::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/categories?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_it_returns_a_category_by_slug(): void
    {
        $category = Category::factory()->create([
            'name' => 'Laptops',
            'slug' => 'laptops',
            'description' => 'Portable computers',
        ]);

        $this->getJson('/api/v1/categories/laptops')
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.name', 'Laptops')
            ->assertJsonPath('data.slug', 'laptops')
            ->assertJsonPath('data.description', 'Portable computers');
    }

    public function test_it_returns_404_for_an_unknown_category_slug(): void
    {
        $this->getJson('/api/v1/categories/unknown-category')
            ->assertNotFound();
    }

}