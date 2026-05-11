<?php
// app/Http/Controllers/LXController.php
// 验证码发送控制器 - 使用Redis缓存，用完即删

namespace App\Http\Controllers;

use App\Mail\VerificationCodeMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class LXController extends \Illuminate\Routing\Controller
{
    // Redis key前缀
    private const VERIFICATION_CODE_PREFIX = 'verification_code:';
    private const RATE_LIMIT_PREFIX = 'rate_limit:';

    /**
     * 发送会员激活验证码
     */
    public function sendVerificationCode(Request $request): JsonResponse
    {
        // 验证请求
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'name' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '参数验证失败',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->input('email');
        $name = $request->input('name');

        $codeKey = self::VERIFICATION_CODE_PREFIX . $email;
        $rateLimitKey = self::RATE_LIMIT_PREFIX . $email;

        // 检查发送频率限制（60秒内只能发送一次）
        if ($this->isRateLimited($rateLimitKey)) {
            $ttl = Redis::ttl($rateLimitKey);
            return response()->json([
                'success' => false,
                'message' => "请等待 {$ttl} 秒后再试",
            ], 429);
        }

        // 如果之前已有验证码，先删除旧的
        if (Redis::exists($codeKey)) {
            Redis::del($codeKey);
        }

        // 生成6位数字验证码
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // 使用Redis缓存验证码（有效期5分钟 = 300秒）
        Redis::setex($codeKey, 300, $code);
        // 设置频率限制（60秒）
        Redis::setex($rateLimitKey, 60, '1');

        try {
            // 发送邮件
            Mail::to($email)->send(new VerificationCodeMail($name, $code));

            return response()->json([
                'success' => true,
                'message' => '验证码已发送至您的邮箱',
                'data' => [
                    'email' => $this->maskEmail($email),
                    'expires_in' => 300, // 5分钟有效期
                ],
            ]);
        } catch (\Exception $e) {
            // 发送失败，立即删除Redis中的验证码
            Redis::del($codeKey);
            Redis::del($rateLimitKey);

            return response()->json([
                'success' => false,
                'message' => '邮件发送失败，请稍后重试',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 验证验证码
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '参数验证失败',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->input('email');
        $code = $request->input('code');
        $codeKey = self::VERIFICATION_CODE_PREFIX . $email;

        // 从Redis获取验证码
        $cachedCode = Redis::get($codeKey);

        if (!$cachedCode) {
            return response()->json([
                'success' => false,
                'message' => '验证码已过期，请重新获取',
            ], 400);
        }

        if ($cachedCode !== $code) {
            return response()->json([
                'success' => false,
                'message' => '验证码错误',
            ], 400);
        }

        // 验证成功，立即从Redis删除验证码（用完即删）
        Redis::del($codeKey);

        return response()->json([
            'success' => true,
            'message' => '验证码验证成功',
        ]);
    }

    /**
     * 检查是否被频率限制
     */
    private function isRateLimited(string $key): bool
    {
        return Redis::exists($key);
    }

    /**
     * 掩码显示邮箱
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1] ?? '';

        $nameLength = strlen($name);
        if ($nameLength <= 2) {
            $maskedName = str_repeat('*', $nameLength);
        } else {
            $maskedName = substr($name, 0, 2) . str_repeat('*', $nameLength - 2);
        }

        return $maskedName . '@' . $domain;
    }
}