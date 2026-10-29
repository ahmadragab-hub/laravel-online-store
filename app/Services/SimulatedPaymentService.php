<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SimulatedPaymentService
{
    public function __construct(
        private readonly OrderLifecycleService $lifecycle,
    ) {
    }

    public function pay(
        User $user,
        Order $order,
        string $idempotencyKey,
        string $outcome,
    ): array {
        return DB::transaction(function () use (
            $user,
            $order,
            $idempotencyKey,
            $outcome,
        ) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            // A retry with the same key returns the original attempt.
            $existingPayment = Payment::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingPayment) {
                if ($existingPayment->order_id !== $lockedOrder->id) {
                    throw new ConflictHttpException(
                        'This idempotency key belongs to another payment attempt.'
                    );
                }

                return [
                    'payment' => $existingPayment,
                    'order' => $lockedOrder->fresh()->load('items'),
                    'replayed' => true,
                ];
            }

            // Expire an overdue reservation before accepting payment.
            if (
                $lockedOrder->status === 'pending'
                && $lockedOrder->inventory_reservation_status === 'reserved'
                && $lockedOrder->reservation_expires_at !== null
                && $lockedOrder->reservation_expires_at->lte(now())
            ) {
                $this->lifecycle->expireReservationIfDue(
                    $lockedOrder->id
                );

                $lockedOrder = Order::query()
                    ->whereKey($lockedOrder->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $payment = $this->recordAttempt(
                    $lockedOrder,
                    $idempotencyKey,
                    'failed',
                );

                return [
                    'payment' => $payment,
                    'order' => $lockedOrder->fresh()->load('items'),
                    'replayed' => false,
                ];
            }

            if (
                $lockedOrder->status !== 'pending'
                || $lockedOrder->inventory_reservation_status !== 'reserved'
                || $lockedOrder->reservation_expires_at === null
            ) {
                throw ValidationException::withMessages([
                    'order' => [
                        'This order does not have a valid payment reservation.',
                    ],
                ]);
            }

            if ($outcome === 'failure') {
                $payment = $this->recordAttempt(
                    $lockedOrder,
                    $idempotencyKey,
                    'failed',
                );

                return [
                    'payment' => $payment,
                    'order' => $lockedOrder->fresh()->load('items'),
                    'replayed' => false,
                ];
            }

            // This service checks the transition and handles a possible
            // expiry race while holding the order lock.
            $updatedOrder = $this->lifecycle->changeStatus(
                $lockedOrder,
                'processing',
            );

            // If the deadline passed just before the transition, the
            // lifecycle service releases inventory instead of processing.
            $paymentStatus = $updatedOrder->status === 'processing'
                ? 'succeeded'
                : 'failed';

            $payment = $this->recordAttempt(
                $lockedOrder,
                $idempotencyKey,
                $paymentStatus,
            );

            return [
                'payment' => $payment,
                'order' => $updatedOrder->fresh()->load('items'),
                'replayed' => false,
            ];
        }, attempts: 3);
    }

    private function recordAttempt(
        Order $order,
        string $idempotencyKey,
        string $status,
    ): Payment {
        return $order->payments()->create([
            'provider' => 'simulated',
            'provider_reference' => 'SIM-' . Str::upper((string) Str::uuid()),
            'idempotency_key' => $idempotencyKey,
            'amount' => $order->total,
            'currency' => $order->currency,
            'status' => $status,
        ]);
    }
}
