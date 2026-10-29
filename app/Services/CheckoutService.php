<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly CatalogCacheService $catalogCache,
    ) {
    }

    public function checkout(User $user, string $key): array
    {
        $existing = Order::query()
            ->where('user_id', $user->id)
            ->where('checkout_idempotency_key', $key)
            ->with('items')
            ->first();

        if ($existing) {
            return ['order' => $existing, 'replayed' => true];
        }

        return DB::transaction(function () use ($user, $key) {
            $cart = $user->cart()->lockForUpdate()->first();

            if (! $cart) {
                throw ValidationException::withMessages([
                    'cart' => ['Your cart is empty.'],
                ]);
            }

            $items = $cart->items()->orderBy('product_id')->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => ['Your cart is empty.'],
                ]);
            }

            $products = Product::query()
                ->whereIn('id', $items->pluck('product_id')->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotalMills = 0;
            $lines = [];

            foreach ($items as $item) {
                $product = $products->get($item->product_id);

                if (! $product || $product->status !== 'active') {
                    throw ValidationException::withMessages([
                        'cart' => ['A product in your cart is unavailable.'],
                    ]);
                }

                if ($product->stock_quantity < $item->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => ["Insufficient stock for {$product->name}."],
                    ]);
                }

                $price = $product->getRawOriginal('price');

                $subtotalMills += $this->toMills($price) * $item->quantity;

                $lines[] = [
                    'product' => $product,
                    'quantity' => $item->quantity,
                    'price' => $price,
                ];
            }

            $subtotal = $this->fromMills($subtotalMills);

            $order = $user->orders()->create([
                'order_number' => 'ORD-' . Str::upper((string) Str::ulid()),
                'status' => 'pending',
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'currency' => 'KWD',
                'checkout_idempotency_key' => $key,
                'reservation_expires_at' => now()->addMinutes(15),
                'inventory_reservation_status' => 'reserved',
            ]);

            foreach ($lines as $line) {
                $product = $line['product'];
                $quantity = $line['quantity'];

                $updated = Product::query()
                    ->whereKey($product->id)
                    ->where('stock_quantity', '>=', $quantity)
                    ->decrement('stock_quantity', $quantity);

                if ($updated !== 1) {
                    throw ValidationException::withMessages([
                        'cart' => ['Insufficient stock.'],
                    ]);
                }

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $line['price'],
                ]);
            }

            $cart->items()->delete();

            $this->catalogCache->invalidate();

            return [
                'order' => $order->load('items'),
                'replayed' => false,
            ];
        }, attempts: 3);
    }

    private function toMills(string $amount): int
    {
        [$whole, $fraction] = array_pad(
            explode('.', $amount, 2),
            2,
            '0'
        );

        $fraction = str_pad(substr($fraction, 0, 3), 3, '0');

        return ((int) $whole * 1000) + (int) $fraction;
    }

    private function fromMills(int $amount): string
    {
        return intdiv($amount, 1000)
            . '.'
            . str_pad((string) ($amount % 1000), 3, '0', STR_PAD_LEFT);
    }
}
