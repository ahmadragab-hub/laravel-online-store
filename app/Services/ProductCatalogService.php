<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductCatalogService
{
    public function __construct(
        private readonly CatalogCacheService $catalogCache,
    ) {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->catalogCache->remember(
            'products.index',
            $filters,
            function () use ($filters) {
                $query = Product::query()
                    ->with('category')
                    ->where('status', 'active');

                $this->applyFilters($query, $filters);

                return $query
                    ->orderBy('id')
                    ->paginate($filters['per_page'] ?? 12)
                    ->withQueryString();
            },
        );
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
    }
}