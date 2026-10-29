<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderLifecycleService
{
    public function __construct(
        private readonly CatalogCacheService $catalogCache,
    ) {
    }

    public function changeStatus(Order $order, string $targetStatus): Order
    {
        return DB::transaction(function () use ($order, $targetStatus) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedOrder->status === 'pending'
                && $targetStatus === 'processing'
                && $lockedOrder->inventory_reservation_status === 'reserved'
                && $lockedOrder->reservation_expires_at !== null
                && $lockedOrder->reservation_expires_at->lte(now())
            ) {
                $this->releaseReservation($lockedOrder, 'expired');

                return $lockedOrder->fresh()->load('items');
            }

            if ($lockedOrder->status === 'pending') {
                if ($targetStatus === 'cancelled') {
                    $this->releaseReservation($lockedOrder, 'cancelled');

                    return $lockedOrder->fresh()->load('items');
                }

                if ($targetStatus === 'processing') {
                    if (
                        $lockedOrder->inventory_reservation_status !== 'reserved'
                        || $lockedOrder->reservation_expires_at === null
                        || $lockedOrder->reservation_expires_at->lte(now())
                    ) {
                        throw ValidationException::withMessages([
                            'status' => [
                                'This order no longer has a valid inventory reservation.',
                            ],
                        ]);
                    }

                    $lockedOrder->update([
                        'status' => 'processing',
                        'inventory_reservation_status' => 'consumed',
                    ]);

                    return $lockedOrder->fresh()->load('items');
                }
            }

            if (
                $lockedOrder->status === 'processing'
                && $targetStatus === 'completed'
                && $lockedOrder->inventory_reservation_status === 'consumed'
            ) {
                $lockedOrder->update([
                    'status' => 'completed',
                ]);

                return $lockedOrder->fresh()->load('items');
            }

            throw ValidationException::withMessages([
                'status' => [
                    "Transition from {$lockedOrder->status} to {$targetStatus} is not allowed.",
                ],
            ]);
        }, attempts: 3);
    }

    public function expireReservationIfDue(int $orderId): bool
    {
        return DB::transaction(function () use ($orderId) {
            $order = Order::query()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return false;
            }

            if (
                $order->status !== 'pending'
                || $order->inventory_reservation_status !== 'reserved'
                || $order->reservation_expires_at === null
                || $order->reservation_expires_at->gt(now())
            ) {
                return false;
            }

            $this->releaseReservation($order, 'expired');

            return true;
        }, attempts: 3);
    }

    public function expireDueReservations(): int
    {
        $expiredCount = 0;

        Order::query()
            ->where('status', 'pending')
            ->where('inventory_reservation_status', 'reserved')
            ->whereNotNull('reservation_expires_at')
            ->where('reservation_expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$expiredCount) {
                foreach ($orders as $order) {
                    if ($this->expireReservationIfDue($order->id)) {
                        $expiredCount++;
                    }
                }
            });

        return $expiredCount;
    }

    private function releaseReservation(
        Order $order,
        string $finalStatus
    ): void {
        if (
            $order->status !== 'pending'
            || $order->inventory_reservation_status !== 'reserved'
        ) {
            throw ValidationException::withMessages([
                'status' => [
                    'This order no longer has a releasable inventory reservation.',
                ],
            ]);
        }

        $items = $order->items()
            ->orderBy('product_id')
            ->get(['product_id', 'quantity']);

        foreach ($items as $item) {
            $product = Product::query()
                ->whereKey($item->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            $product->update([
                'stock_quantity' =>
                    $product->stock_quantity + $item->quantity,
            ]);
        }

        $order->update([
            'status' => $finalStatus,
            'inventory_reservation_status' => 'released',
        ]);

        $this->catalogCache->invalidate();
    }
}
