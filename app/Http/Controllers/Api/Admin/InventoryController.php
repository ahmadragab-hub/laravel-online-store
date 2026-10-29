<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\InventoryAdjustmentRequest;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(
        private readonly CatalogCacheService $catalogCache,
    ) {
    }

    public function store(
        InventoryAdjustmentRequest $request,
        Product $product
    ): JsonResponse {
        $adjustment = DB::transaction(function () use ($request, $product) {
            $lockedProduct = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $lockedProduct->stock_quantity;
            $change = $request->validated('quantity_change');
            $after = $before + $change;

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity_change' => [
                        'The adjustment cannot reduce stock below zero.',
                    ],
                ]);
            }

            $lockedProduct->update([
                'stock_quantity' => $after,
            ]);

            return $lockedProduct->inventoryAdjustments()->create([
                'user_id' => $request->user()->id,
                'quantity_before' => $before,
                'quantity_change' => $change,
                'quantity_after' => $after,
                'reason' => $request->validated('reason'),
            ]);
        }, attempts: 3);

        $this->catalogCache->invalidate();

        return response()->json([
            'message' => 'Inventory adjusted successfully.',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'stock_quantity' => $adjustment->quantity_after,
            ],
            'adjustment' => [
                'id' => $adjustment->id,
                'quantity_before' => $adjustment->quantity_before,
                'quantity_change' => $adjustment->quantity_change,
                'quantity_after' => $adjustment->quantity_after,
                'reason' => $adjustment->reason,
                'created_at' => $adjustment->created_at,
            ],
        ]);
    }
}
