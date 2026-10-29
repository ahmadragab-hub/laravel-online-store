<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\SimulatedPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SimulatedPaymentController extends Controller
{
    public function store(
        Request $request,
        Order $order,
        SimulatedPaymentService $payments,
    ): JsonResponse {
        // Never expose a customer-selectable payment outcome in production.
        abort_unless(
            app()->environment(['local', 'testing']),
            404,
        );

        abort_unless(
            (int) $order->user_id === (int) $request->user()->id,
            404,
        );

        $validated = Validator::make([
            'idempotency_key' => $request->header('Idempotency-Key'),
            'outcome' => $request->input('outcome'),
        ], [
            'idempotency_key' => ['required', 'string', 'min:16', 'max:255'],
            'outcome' => ['required', Rule::in(['success', 'failure'])],
        ])->validate();

        $result = $payments->pay(
            $request->user(),
            $order,
            $validated['idempotency_key'],
            $validated['outcome'],
        );

        $statusCode = $result['order']->status === 'expired'
            ? 409
            : ($result['replayed'] ? 200 : 201);

        return response()->json([
            'message' => match ($result['payment']->status) {
                'succeeded' => 'Simulated payment succeeded.',
                default => $result['order']->status === 'expired'
                    ? 'The reservation expired; payment was not accepted.'
                    : 'Simulated payment failed.',
            },
            'payment' => $result['payment'],
            'order' => new OrderResource($result['order']),
            'replayed' => $result['replayed'],
        ], $statusCode);
    }
}
