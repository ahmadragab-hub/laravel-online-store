<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogCacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CatalogCacheService $catalogCache,
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        return JsonResource::collection(
            Category::query()->latest()->paginate(15)
        );
    }

    public function store(Request $request): JsonResource
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug'],
            'description' => ['nullable', 'string'],
        ]);

        $category = Category::query()->create($data);

        $this->catalogCache->invalidate();

        return new JsonResource($category);
    }

    public function show(Category $category): JsonResource
    {
        return new JsonResource($category);
    }

    public function update(Request $request, Category $category): JsonResource
    {
        $data = $request->validate([
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('categories', 'name')->ignore($category->id),
            ],
            'slug' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('categories', 'slug')->ignore($category->id),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $category->update($data);

        $this->catalogCache->invalidate();

        return new JsonResource($category->fresh());
    }

    public function destroy(Category $category): JsonResponse|\Illuminate\Http\Response
    {
        if (Product::query()->where('category_id', $category->id)->exists()) {
            return response()->json([
                'message' => 'This category contains products. Move or remove them before deleting it.',
            ], 409);
        }

        $category->delete();

        $this->catalogCache->invalidate();

        return response()->noContent();
    }
}
