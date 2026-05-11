<?php

use App\Http\Controllers\FmyController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [FmyController::class, 'login']);
    Route::post('logout', [FmyController::class, 'logout'])->middleware('auth:employee');
});

Route::middleware('auth:employee')->group(function () {
    Route::post('orders', [FmyController::class, 'createOrder']);
    Route::get('orders/{id}', [FmyController::class, 'getOrderDetail']);
});
