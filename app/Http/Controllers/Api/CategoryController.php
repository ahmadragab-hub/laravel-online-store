<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CatalogCacheService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Requests\Api\CategoryIndexRequest;

class CategoryController extends Controller
{
    public function index(
        CategoryIndexRequest $request,
        CatalogCacheService $catalogCache,
    ): AnonymousResourceCollection {
        $validated = $request->validated();
        $perPage = $validated['per_page'] ?? 20;

        $categories = $catalogCache->remember(
            'categories.index',
            ['per_page' => $perPage],
            fn () => Category::query()
                ->orderBy('name')
                ->paginate($perPage)
                ->withQueryString(),
        );

        return CategoryResource::collection($categories);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }
}
