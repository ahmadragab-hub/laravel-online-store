
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('order_number')->unique();

            $table->string('status', 30)->default('pending');

            $table->decimal('subtotal', 12, 3);
            $table->decimal('total', 12, 3);
            $table->char('currency', 3)->default('KWD');

            $table->json('shipping_address')->nullable();

            // Prevent duplicate checkout requests.
            $table->string('checkout_idempotency_key')->nullable();

            // Inventory reservation lifecycle.
            $table->timestamp('reservation_expires_at')->nullable();
            $table->string('inventory_reservation_status', 20)
                ->default('reserved');

            $table->timestamps();

            $table->unique([
                'user_id',
                'checkout_idempotency_key',
            ], 'orders_user_checkout_key_unique');

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index([
                'inventory_reservation_status',
                'reservation_expires_at',
            ], 'orders_reservation_expiry_index');
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
