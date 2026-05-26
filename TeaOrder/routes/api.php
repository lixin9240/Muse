<?php

use App\Http\Controllers\FmyController;
use App\Http\Controllers\LXController;
use App\Http\Controllers\WjcController;
use Illuminate\Support\Facades\Route;


// ==================== 认证模块（无需登录）====================
Route::prefix('auth')->group(function () {
<<<<<<< Updated upstream
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
=======
    Route::post('login', [FmyController::class, 'login']);                                    // 员工登录
    Route::post('logout', [FmyController::class, 'logout'])->middleware(['single-session', 'auth:employee']); // 员工登出
});

// 会员邮箱验证
Route::post('customers/verify-email', [LXController::class, 'verifyEmail']);                 // 验证邮箱激活会员

/*
|--------------------------------------------------------------------------
| 需要认证的接口（员工登录后）
|--------------------------------------------------------------------------
*/
Route::middleware(['single-session', 'auth:employee'])->group(function () {

    // ==================== 订单模块 ====================
    Route::prefix('orders')->group(function () {
        Route::post('/', [FmyController::class, 'createOrder']);                              // 创建订单
        Route::get('/{id}', [FmyController::class, 'getOrderDetail']);                        // 查看订单详情
    });
>>>>>>> Stashed changes

    // ==================== 员工管理模块 ====================
    Route::prefix('employees')->group(function () {
        Route::post('/', [FmyController::class, 'addEmployee']);                               // 添加店员
        Route::get('/', [FmyController::class, 'listEmployees']);                              // 查看所有店员
        Route::get('/{employeeId}', [FmyController::class, 'getEmployeeDetail']);              // 查看店员详情
        Route::delete('/{employeeId}', [FmyController::class, 'deleteEmployee']);               // 删除店员
    });

<<<<<<< Updated upstream
Route::middleware('auth:employee')->group(function () {
    // 菜单模块
    Route::get('products', [WjcController::class, 'index']);
    Route::post('products', [WjcController::class, 'store'])->middleware('role:manager');
    
    // 数据看板模块
=======
    // ==================== 会员管理模块 ====================
    Route::prefix('customers')->group(function () {
        Route::post('/', [LXController::class, 'store']);                                       // 创建顾客
        Route::match(['get', 'post'], '/{id}/send-verify-email', [LXController::class, 'sendVerifyEmail']); // 发送验证邮件
        Route::get('/', [LXController::class, 'show']);                                         // 查询顾客详情（通过手机号）
    });

    // ==================== 产品/饮品模块（含CRUD + OSS文件管理）====================
    Route::prefix('products')->group(function () {
        // 公开查询（所有已登录员工）
        Route::get('/', [WjcController::class, 'index'])->name('products.index');              // 饮品列表（支持分类、搜索、缓存）
        Route::get('/{id}', [WjcController::class, 'show'])->name('products.show');             // 产品详情（含规格、材料）

        // 管理员操作（需要 manager 角色）
        Route::middleware('role:manager')->group(function () {
            Route::post('/', [WjcController::class, 'store'])->name('products.store');          // 创建产品（含规格、材料、图片关联）
            Route::put('/{id}', [WjcController::class, 'update'])->name('products.update');     // 更新产品（自动清理旧图片）
            Route::delete('/{id}', [WjcController::class, 'destroy'])->name('products.destroy'); // 删除产品（级联清理OSS文件）

            // 图片上传与管理
            Route::post('/upload-image', [WjcController::class, 'uploadProductImage'])
                ->name('products.upload-image');                                                 // 上传产品图片到OSS
            Route::delete('/images/{imageId}', [WjcController::class, 'deleteProductImage']);//删除图片接口
        });
    });

    // ==================== 数据看板模块 ====================
>>>>>>> Stashed changes
    Route::prefix('dashboard')->group(function () {
        Route::get('/today', [WjcController::class, 'today'])->name('dashboard.today');         // 今日营业数据概览
        Route::get('/ranking', [WjcController::class, 'ranking'])->name('dashboard.ranking');   // 产品销售排行榜
        Route::get('/members', [WjcController::class, 'members'])->name('dashboard.members');   // 会员统计分析
    });

    // ==================== 门店管理模块 ====================
    Route::prefix('stores')->group(function () {
        Route::post('/', [LXController::class, 'storeStore']);                                  // 添加门店
        Route::get('/', [LXController::class, 'indexStore']);                                   // 获取门店列表
        Route::put('/{id}', [LXController::class, 'updateStore']);                              // 更新门店信息
    });
});
<<<<<<< Updated upstream

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
=======
>>>>>>> Stashed changes
