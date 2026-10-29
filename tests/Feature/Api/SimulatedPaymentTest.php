<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SimulatedPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const IDEMPOTENCY_KEY = 'pay-test-key-0001';

    public function test_successful_payment_moves_order_to_processing(): void
    {
        [$customer, $order, $product] = $this->makePayableOrder();

        Sanctum::actingAs($customer);

        $this->postSimulatedPayment($order, 'success')
            ->assertCreated()
            ->assertJsonPath('payment.status', 'succeeded')
            ->assertJsonPath('order.status', 'processing')
            ->assertJsonPath(
                'order.inventory_reservation_status',
                'consumed'
            )
            ->assertJsonPath('replayed', false);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'processing',
            'inventory_reservation_status' => 'consumed',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);
    }

    public function test_failed_payment_keeps_pending_reservation(): void
    {
        [$customer, $order, $product] = $this->makePayableOrder();

        Sanctum::actingAs($customer);

        $this->postSimulatedPayment($order, 'failure')
            ->assertCreated()
            ->assertJsonPath('payment.status', 'failed')
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath(
                'order.inventory_reservation_status',
                'reserved'
            );

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);
    }

    public function test_replaying_the_same_idempotency_key_returns_the_original_attempt(): void
    {
        [$customer, $order] = $this->makePayableOrder();

        Sanctum::actingAs($customer);

        $first = $this->postSimulatedPayment($order, 'success')
            ->assertCreated();

        $paymentId = $first->json('payment.id');

        $this->postSimulatedPayment($order, 'failure')
            ->assertOk()
            ->assertJsonPath('payment.id', $paymentId)
            ->assertJsonPath('payment.status', 'succeeded')
            ->assertJsonPath('replayed', true);

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_payment_on_expired_reservation_returns_conflict_and_restores_stock(): void
    {
        [$customer, $order, $product] = $this->makePayableOrder();

        $order->update([
            'reservation_expires_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($customer);

        $this->postSimulatedPayment($order, 'success')
            ->assertConflict()
            ->assertJsonPath('payment.status', 'failed')
            ->assertJsonPath('order.status', 'expired')
            ->assertJsonPath(
                'order.inventory_reservation_status',
                'released'
            );

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);
    }

    public function test_customer_cannot_pay_for_another_customers_order(): void
    {
        [, $order] = $this->makePayableOrder();

        $otherCustomer = User::factory()->create();

        Sanctum::actingAs($otherCustomer);

        $this->postSimulatedPayment($order, 'success')->assertNotFound();
    }

    public function test_idempotency_key_cannot_be_reused_for_a_different_order(): void
    {
        [$firstCustomer, $firstOrder] = $this->makePayableOrder();
        [$secondCustomer, $secondOrder] = $this->makePayableOrder();

        Sanctum::actingAs($firstCustomer);

        $this->postSimulatedPayment($firstOrder, 'success')->assertCreated();

        Sanctum::actingAs($secondCustomer);

        $this->withHeader('Idempotency-Key', self::IDEMPOTENCY_KEY)
            ->postJson(
                "/api/v1/orders/{$secondOrder->id}/simulated-payment",
                ['outcome' => 'success']
            )
            ->assertConflict();
    }

    public function test_simulated_payment_is_unavailable_outside_local_and_testing(): void
    {
        [$customer, $order] = $this->makePayableOrder();

        $this->app->detectEnvironment(fn () => 'production');

        Sanctum::actingAs($customer);

        $this->postSimulatedPayment($order, 'success')->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Order, 2: Product}
     */
    private function makePayableOrder(): array
    {
        $customer = User::factory()->create();

        $product = Product::factory()->create([
            'stock_quantity' => 8,
            'status' => 'active',
        ]);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => 'pending',
            'inventory_reservation_status' => 'reserved',
            'reservation_expires_at' => now()->addMinutes(15),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => $product->getRawOriginal('price'),
        ]);

        return [$customer, $order, $product];
    }

    private function postSimulatedPayment(Order $order, string $outcome)
    {
        return $this->withHeader('Idempotency-Key', self::IDEMPOTENCY_KEY)
            ->postJson(
                "/api/v1/orders/{$order->id}/simulated-payment",
                ['outcome' => $outcome]
            );
    }
}
