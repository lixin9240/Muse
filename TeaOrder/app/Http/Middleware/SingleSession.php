<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SingleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'code' => 401,
                    'message' => '未提供认证令牌',
                    'data' => null,
                ], 401);
            }

            $user = $request->user('employee');

            if (!$user) {
                return response()->json([
                    'code' => 401,
                    'message' => '令牌无效或已过期',
                    'data' => null,
                ], 401);
            }

            $cachedToken = Cache::get('employee_token:' . $user->id);

            if (!$cachedToken) {
                return response()->json([
                    'code' => 401,
                    'message' => '登录已失效，请重新登录',
                    'data' => [
                        'reason' => 'session_expired',
                        'suggestion' => '您的账号已登出或session已过期',
                    ],
                ], 401);
            }

            if ($token !== $cachedToken) {
                return response()->json([
                    'code' => 401,
                    'message' => '账号已在其他设备登录',
                    'data' => [
                        'reason' => 'single_session_invalidated',
                        'suggestion' => '请重新登录以继续使用',
                    ],
                ], 401);
            }

        } catch (\Exception $e) {
            return response()->json([
                'code' => 401,
                'message' => '认证失败：' . $e->getMessage(),
                'data' => null,
            ], 401);
        }

        return $next($request);
    }
}