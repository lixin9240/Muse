<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user('employee');
        
        if (!$user) {
            return response()->json([
                'code' => 4010,
                'message' => '未登录或令牌已过期',
                'data' => null
            ], 401);
        }

        if ($user->role !== $role) {
            return response()->json([
                'code' => 4030,
                'message' => '权限不足，仅' . match($role) {
                    'manager' => '管理员',
                    'staff' => '店员',
                    default => $role
                } . '可操作',
                'data' => null
            ], 403);
        }

        return $next($request);
    }
}