<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'provider',
        'provider_reference',
        'idempotency_key',
        'amount',
        'currency',
        'status',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'reconciled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
