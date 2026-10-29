<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddCartItemRequest;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cart = $request->user()->cart()->firstOrCreate([]);

        $cart->load('items.product');

        return response()->json([
            'cart' => $cart,
        ]);
    }

    public function store(AddCartItemRequest $request): JsonResponse
    {
        $data = $request->validated();

        $cart = $request->user()->cart()->firstOrCreate([]);

        $item = DB::transaction(function () use ($cart, $data) {
            $product = Product::query()
                ->where('status', 'active')
                ->findOrFail($data['product_id']);

            $item = $cart->items()
                ->where('product_id', $product->id)
                ->first();

            $quantity = ($item?->quantity ?? 0) + $data['quantity'];

            if ($quantity > $product->stock_quantity) {
                abort(422, 'Requested quantity exceeds available stock.');
            }

            if ($item) {
                $item->update(['quantity' => $quantity]);

                return $item->fresh('product');
            }

            return $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
            ])->load('product');
        });

        return response()->json(['item' => $item], 201);
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        $cart = $request->user()->cart()->first();

        abort_unless(
            $cart && $cartItem->cart_id === $cart->id,
            404
        );

        $cartItem->delete();

        return response()->json([
            'message' => 'Cart item removed.',
        ]);
    }
}
