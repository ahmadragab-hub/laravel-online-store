<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $categoryNames = [
            'Laptops',
            'Phones',
            'Accessories',
            'Monitors',
        ];

        foreach ($categoryNames as $name) {
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => "{$name} products",
                ]
            );

            Product::factory()
                ->count(5)
                ->create([
                    'category_id' => $category->id,
                ]);
        }
    }
}
