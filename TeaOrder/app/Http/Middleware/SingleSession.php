<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

//判断当前token是不是最新的
class SingleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = $request->bearerToken();//从请求头中提取 Bearer Token

            if (!$token) {
                return response()->json([
                    'code' => 401,
                    'message' => '未提供认证令牌',
                    'data' => null,
                ], 401);
            }

            ////使用 employee 守卫来解析 JWT，如果 token 有效，返回的就是数据库 employees 表中的一条记录
            $user = $request->user('employee');

            if (!$user) {
                return response()->json([
                    'code' => 401,
                    'message' => '令牌无效或已过期',
                    'data' => null,
                ], 401);
            }

            //获取缓存中这个员工的"最新有效 token"
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

        //所有校验都通过了，把请求传给下一个中间件或控制器。
        //$next:当前中间件之后的所有中间件 + 最终的控制器方法
        return $next($request);
    }
}
