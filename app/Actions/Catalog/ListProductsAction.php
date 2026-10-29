<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListProductsAction
{
    public function execute(array $filters): LengthAwarePaginator
    {
        return Product::query()
            ->with('category')
            ->where('status', 'active')
            ->when(
                $filters['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $filters['category_id'] ?? null,
                fn ($query, $categoryId) =>
                    $query->where('category_id', $categoryId)
            )
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 12)
            ->withQueryString();
    }
}
