<?php

use App\Http\Controllers\FmyController;
use App\Http\Controllers\LXController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [FmyController::class, 'login']);
    Route::post('logout', [FmyController::class, 'logout'])->middleware('auth:employee');
});

Route::middleware('auth:employee')->group(function () {
    Route::post('orders', [FmyController::class, 'createOrder']);
    Route::get('orders/{id}', [FmyController::class, 'getOrderDetail']);
});

// ==================== 会员模块（含QQ邮箱验证）====================
// 需要店员登录的接口
Route::middleware('auth:employee')->prefix('customers')->group(function () {
    Route::post('/', [LXController::class, 'store']);                    // 1. 创建顾客
    Route::match(['get', 'post'], '{id}/send-verify-email', [LXController::class, 'sendVerifyEmail']);  // 2. 发送QQ邮箱验证邮件
    Route::get('/', [LXController::class, 'show']);                     // 4. 查看顾客详情（通过手机号查询）
});

// 不需要认证的接口（顾客自己操作）
Route::post('customers/verify-email', [LXController::class, 'verifyEmail']);  // 3. 验证邮箱激活会员

