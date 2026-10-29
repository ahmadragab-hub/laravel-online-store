<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedInteger('quantity_before');
            $table->integer('quantity_change');
            $table->unsignedInteger('quantity_after');
            $table->string('reason', 500);

            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
