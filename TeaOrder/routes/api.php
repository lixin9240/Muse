<?php

use App\Http\Controllers\FmyController;
use App\Http\Controllers\LXController;
use App\Http\Controllers\WjcController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [FmyController::class, 'login']);
    Route::post('logout', [FmyController::class, 'logout'])->middleware('auth:employee');
});

Route::middleware(['single-session', 'auth:employee'])->group(function () {
    Route::post('orders', [FmyController::class, 'createOrder']);
    Route::get('orders/{id}', [FmyController::class, 'getOrderDetail']);
});


// 员工管理模块（已移至下方统一认证中间件组中，此处保留注释作为参考）
// Route::prefix('stores')->group(function () {
//     Route::post('{storeId}/employees', [FmyController::class, 'addEmployee']);
//     Route::get('{storeId}/employees', [FmyController::class, 'listEmployees']);
//     Route::delete('{storeId}/employees/{employeeId}', [FmyController::class, 'deleteEmployee']);
// });

// ==================== 会员模块（含QQ邮箱验证）====================
// 需要店员登录的接口
Route::middleware(['single-session', 'auth:employee'])->prefix('customers')->group(function () {
    Route::post('/', [LXController::class, 'store']);                    // 1. 创建顾客
    Route::match(['get', 'post'], '{id}/send-verify-email', [LXController::class, 'sendVerifyEmail']);  // 2. 发送QQ邮箱验证邮件
    Route::get('/', [LXController::class, 'show']);                     // 4. 查看顾客详情（通过手机号查询）
});

// 不需要认证的接口（顾客自己操作）
Route::post('customers/verify-email', [LXController::class, 'verifyEmail']);  // 3. 验证邮箱激活会员

Route::middleware(['single-session', 'auth:employee'])->group(function () {
    // 菜单模块
    Route::get('products', [WjcController::class, 'index']);
    Route::post('products', [WjcController::class, 'store'])->middleware('role:manager');

    // 数据看板模块
    Route::prefix('dashboard')->group(function () {
        Route::get('today', [WjcController::class, 'today']);
        Route::get('ranking', [WjcController::class, 'ranking']);
        Route::get('members', [WjcController::class, 'members']);
    });

    // 员工管理模块
    Route::prefix('stores')->group(function () {
        Route::post('{storeId}/employees', [FmyController::class, 'addEmployee']);
        Route::get('{storeId}/employees', [FmyController::class, 'listEmployees']);
        Route::get('{storeId}/employees/{employeeId}', [FmyController::class, 'getEmployeeDetail']);
        Route::delete('{storeId}/employees/{employeeId}', [FmyController::class, 'deleteEmployee']);
    });
});


// 门店模块
Route::middleware('auth:employee')->prefix('stores')->group(function () {
    Route::post('/', [LXController::class, 'storeStore']);           // 6.1 添加门店
    Route::get('/', [LXController::class, 'indexStore']);            // 6.2 获取门店列表
    Route::put('/{id}', [LXController::class, 'updateStore']);       // 6.3 更新门店信息
});


//文件上传模块（OSS直传）
Route::middleware('auth:employee')->prefix('upload')->group(function () {
    Route::post('/signature', [LXController::class, 'getOssSignature']);  // 获取OSS上传签名
    Route::post('/callback', [LXController::class, 'ossCallback']);       // OSS上传回调
});
