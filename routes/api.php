<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\InventoryController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Routes
    |--------------------------------------------------------------------------
    */

    Route::post('/register', [AuthController::class, 'register'])
        ->name('api.v1.register');

    Route::post('/login', [AuthController::class, 'login'])
        ->name('api.v1.login');

    Route::get('/categories', [CategoryController::class, 'index'])
        ->name('api.v1.categories.index');

    Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])
        ->name('api.v1.categories.show');

    Route::get('/products', [ProductController::class, 'index'])
        ->name('api.v1.products.index');

    Route::get('/products/{product}', [ProductController::class, 'show'])
        ->name('api.v1.products.show');


    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'role:admin'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {


            // Inventory
            Route::post(
                '/products/{product}/inventory-adjustments',
                [InventoryController::class, 'store']
            )->name('products.inventory-adjustments.store');

            // Orders
            Route::get('/orders', [Admin\OrderController::class, 'index'])
                ->name('orders.index');

            Route::get('/orders/{order}', [Admin\OrderController::class, 'show'])
                ->name('orders.show');

            Route::patch(
                '/orders/{order}/status',
                [Admin\OrderController::class, 'updateStatus']
            )->name('orders.update-status');

            // Products
            Route::apiResource('products', AdminProductController::class);

            // Categories
            Route::apiResource('categories', AdminCategoryController::class);
        });


    /*
    |--------------------------------------------------------------------------
    | Authenticated Customer Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {

        // Authentication
        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('api.v1.logout');

        Route::get('/me', [AuthController::class, 'me'])
            ->name('api.v1.me');


        // Cart
        Route::get('/cart', [CartController::class, 'index'])
            ->name('api.v1.cart.index');

        Route::post('/cart/items', [CartController::class, 'store'])
            ->name('api.v1.cart.items.store');

        Route::delete(
            '/cart/items/{cartItem}',
            [CartController::class, 'destroy']
        )->name('api.v1.cart.items.destroy');


        // Checkout
        Route::post('/checkout', [CheckoutController::class, 'store'])
            ->name('api.v1.checkout.store');


        // Customer Orders
        Route::get('/orders', [OrderController::class, 'index'])
            ->name('api.v1.orders.index');

        Route::get('/orders/{order}', [OrderController::class, 'show'])
            ->name('api.v1.orders.show');

        Route::post(
            '/orders/{order}/simulated-payment',
            [\App\Http\Controllers\Api\SimulatedPaymentController::class, 'store']
        )->name('api.v1.orders.simulated-payment');
    });
});
