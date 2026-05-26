<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        \App\Providers\OssStorageServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'single-session' => \App\Http\Middleware\SingleSession::class,
        ]);

        // 配置认证失败时不重定向，返回 JSON 响应
        $middleware->redirectGuestsTo(function ($request) {
            return response()->json([
                'code' => 4010,
                'message' => '未登录或令牌无效',
                'data' => null,
            ], 401);
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e, $request) {
            return response()->json([
                'code' => 4010,
                'message' => '令牌无效',
                'data' => null,
            ], 401);
        });

        $exceptions->render(function (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e, $request) {
            return response()->json([
                'code' => 4011,
                'message' => '令牌已过期，请重新登录',
                'data' => null,
            ], 401);
        });

        $exceptions->render(function (\Tymon\JWTAuth\Exceptions\TokenBlacklistedException $e, $request) {
            return response()->json([
                'code' => 4012,
                'message' => '令牌已失效，请重新登录',
                'data' => null,
            ], 401);
        });

        $exceptions->render(function (\Tymon\JWTAuth\Exceptions\JWTException $e, $request) {
            return response()->json([
                'code' => 4010,
                'message' => '认证失败：令牌解析错误',
                'data' => null,
            ], 401);
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            return response()->json([
                'code' => 4010,
                'message' => '未登录或令牌无效',
                'data' => null,
            ], 401);
        });
    })->create();
