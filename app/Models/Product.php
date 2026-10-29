<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'stock_quantity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:3',
            'stock_quantity' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }
}
