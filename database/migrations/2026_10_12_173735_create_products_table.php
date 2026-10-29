
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->decimal('price', 12, 3);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->index('status');
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
