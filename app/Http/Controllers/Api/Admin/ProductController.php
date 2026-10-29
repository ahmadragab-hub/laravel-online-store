<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\CatalogCacheService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(
        private readonly CatalogCacheService $catalogCache,
    ) {
    }
    
    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection(
            Product::query()
                ->with('category')
                ->latest()
                ->paginate(15)
        );
    }

    public function store(Request $request): ProductResource
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'gt:0'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $product = Product::query()->create($data);

        $this->catalogCache->invalidate();

        return new ProductResource($product->load('category'));
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load('category'));
    }

    public function update(Request $request, Product $product): ProductResource
    {
        $data = $request->validate([
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($product->id),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
        ]);

        $product->update($data);

        $this->catalogCache->invalidate();

        return new ProductResource($product->fresh()->load('category'));
    }

    public function destroy(Product $product)
    {
        if ($product->orderItems()->exists()) {
            return response()->json([
                'message' => 'This product belongs to existing orders. Deactivate it instead.',
            ], 409);
        }

        $product->delete();

        $this->catalogCache->invalidate();

        return response()->noContent();
    }
}
