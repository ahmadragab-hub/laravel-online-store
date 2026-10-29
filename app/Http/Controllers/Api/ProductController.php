<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductIndexRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\CatalogCacheService;
use App\Services\ProductCatalogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(
        ProductIndexRequest $request,
        ProductCatalogService $catalog
    ): AnonymousResourceCollection {
        $products = $catalog->paginate($request->validated());

        return ProductResource::collection($products);
    }

    public function show(
        string $product,
        CatalogCacheService $catalogCache,
    ): ProductResource {
        $model = $catalogCache->remember(
            'products.show',
            ['slug' => $product],
            fn () => Product::query()
                ->with('category')
                ->where('status', 'active')
                ->where('slug', $product)
                ->firstOrFail(),
        );

        return new ProductResource($model);
    }
}