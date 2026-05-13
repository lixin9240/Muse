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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'single-session' => \App\Http\Middleware\SingleSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e, $request) {
            return response()->json([
                'code' => 401,
                'message' => '令牌无效',
                'data' => null,
            ], 401);
        });

        $exceptions->render(function (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e, $request) {
            return response()->json([
                'code' => 401,
                'message' => '令牌已过期，请重新登录',
                'data' => null,
            ], 401);
        });

        $exceptions->render(function (\Tymon\JWTAuth\Exceptions\TokenBlacklistedException $e, $request) {
            return response()->json([
                'code' => 401,
                'message' => '令牌已失效，请重新登录',
                'data' => null,
            ], 401);
        });

        $exceptions->render(function (\Tymon\JWTAuth\Exceptions\JWTException $e, $request) {
            return response()->json([
                'code' => 401,
                'message' => '认证失败：令牌解析错误',
                'data' => null,
            ], 401);
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或令牌无效',
                'data' => null,
            ], 401);
        });
    })->create();
