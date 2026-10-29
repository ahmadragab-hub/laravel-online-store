<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return OrderResource::collection(
            Order::query()
                ->with('items')
                ->latest()
                ->paginate(15)
        );
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load('items'));
    }

    public function updateStatus(
        Request $request,
        Order $order,
        OrderLifecycleService $lifecycle
    ): JsonResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in(['completed', 'cancelled']),
            ],
        ]);

        $updatedOrder = $lifecycle->changeStatus(
            $order,
            $validated['status']
        );

        if (
            $updatedOrder->status === 'expired'
            && $validated['status'] === 'processing'
        ) {
            return response()->json([
                'message' => 'The reservation expired. Inventory was released.',
                'order' => new OrderResource($updatedOrder),
            ], 409);
        }

        return response()->json([
            'message' => 'Order status updated.',
            'order' => new OrderResource($updatedOrder),
        ]);
    }
}
