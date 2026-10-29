<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-' . Str::upper((string) Str::ulid()),
            'status' => 'pending',
            'subtotal' => '20.000',
            'total' => '20.000',
            'currency' => 'KWD',
            'shipping_address' => null,
            'checkout_idempotency_key' => null,
            'reservation_expires_at' => now()->addMinutes(15),
            'inventory_reservation_status' => 'reserved',
        ];
    }
}
