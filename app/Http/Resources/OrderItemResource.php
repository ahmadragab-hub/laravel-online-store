<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'line_total' => number_format(
                (int) round((float) $this->unit_price * 1000)
                    * $this->quantity / 1000,
                3,
                '.',
                ''
            ),
        ];
    }
}
