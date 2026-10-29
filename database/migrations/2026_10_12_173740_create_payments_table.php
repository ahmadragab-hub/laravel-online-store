
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('provider', 30)->default('simulated');
            $table->string('provider_reference')->nullable();

            // Unique for each payment attempt.
            $table->string('idempotency_key')->unique();

            $table->decimal('amount', 12, 3);
            $table->char('currency', 3)->default('KWD');
            $table->string('status', 20)->default('pending');

            // Used to flag payment events needing investigation.
            $table->timestamp('reconciled_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['provider', 'provider_reference'],
                'payments_provider_reference_unique'
            );

            $table->index(['order_id', 'status']);
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
