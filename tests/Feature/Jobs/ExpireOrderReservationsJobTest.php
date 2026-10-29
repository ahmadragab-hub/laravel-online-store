<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ExpireOrderReservationsJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExpireOrderReservationsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_expires_due_reservations(): void
    {
        $customer = User::factory()->create();

        $product = Product::factory()->create([
            'stock_quantity' => 8,
        ]);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => 'pending',
            'inventory_reservation_status' => 'reserved',
            'reservation_expires_at' => now()->subMinute(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => $product->getRawOriginal('price'),
        ]);

        $job = new ExpireOrderReservationsJob();
        $job->handle(app(OrderLifecycleService::class));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'expired',
            'inventory_reservation_status' => 'released',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);
    }

    public function test_artisan_command_dispatches_the_expiry_job(): void
    {
        Queue::fake();

        $this->artisan('orders:expire-reservations')->assertExitCode(0);

        Queue::assertPushed(ExpireOrderReservationsJob::class);
    }
}
