<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'currency' => $this->currency,
            'shipping_address' => $this->shipping_address,
            'reservation_expires_at' => $this->reservation_expires_at,
            'inventory_reservation_status' =>
                $this->inventory_reservation_status,
            'items' => OrderItemResource::collection(
                $this->whenLoaded('items')
            ),
            'created_at' => $this->created_at,
        ];
    }
}
