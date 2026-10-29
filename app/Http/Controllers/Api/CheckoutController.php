<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Api\CheckoutRequest;
use Illuminate\Support\Facades\Validator;

class CheckoutController extends Controller
{
    public function store(
        CheckoutRequest $request,
        CheckoutService $checkoutService
    ): JsonResponse {

        $result = $checkoutService->checkout(
            $request->user(),
            $request->validated('idempotency_key')
        );

        return response()->json(
            ['order' => $result['order']],
            $result['replayed'] ? 200 : 201
        );
    }
}
