<?php
// app/Http/Controllers/LXController.php
// 会员模块控制器 - 包含QQ邮箱验证功能

namespace App\Http\Controllers;

use App\Mail\VerificationCodeMail;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class LXController extends \Illuminate\Routing\Controller
{
    // ==================== 常量定义 ====================
    private const VERIFY_CODE_PREFIX = 'verify_code:customer:';
    private const RATE_LIMIT_PREFIX = 'verify_code:limit:';
    private const SHOP_NAME = 'xx奶茶店';

    // ==================== 1. 创建顾客 ====================
    /**
     * POST /api/customers — 创建顾客
     * 顾客首次到店，店员录入手机号
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'name' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors()->toArray(), 422);
        }

        // 检查手机号是否已存在
        $existingCustomer = Customer::where('phone', $request->phone)->first();
        if ($existingCustomer) {
            return $this->error('该手机号已存在', 4001, [], 400);
        }

        // 创建顾客（默认为guest普通顾客）
        $customer = Customer::create([
            'phone' => $request->phone,
            'name' => $request->name,
            'type' => 'guest',
            'level' => 'none',
            'is_verified' => false,
            'total_spent' => 0,
            'order_count' => 0,
        ]);

        return $this->success('顾客创建成功', [
            'id' => $customer->id,
            'phone' => $customer->phone,
            'name' => $customer->name,
            'type' => $customer->type,
            'level' => $customer->level,
            'is_verified' => $customer->is_verified,
            'total_spent' => $customer->total_spent,
            'order_count' => $customer->order_count,
            'tip' => '提供QQ邮箱可升级为会员享受折扣',
        ]);
    }

    // ==================== 2. 发送QQ邮箱验证邮件 ====================
    /**
     * POST /api/customers/{id}/send-verify-email — 发送QQ邮箱验证邮件
     */
    public function sendVerifyEmail(Request $request, int $id): JsonResponse
    {
        // 调试：检查PHP配置
        \Illuminate\Support\Facades\Log::info('PHP Version: ' . PHP_VERSION);
        \Illuminate\Support\Facades\Log::info('Loaded php.ini: ' . php_ini_loaded_file());
        \Illuminate\Support\Facades\Log::info('Redis extension loaded: ' . (extension_loaded('redis') ? 'YES' : 'NO'));
        
        $customer = Customer::find($id);
        if (!$customer) {
            return $this->error('顾客不存在', 404, [], 404);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors()->toArray());
        }

        $email = $request->email;

        // 校验：必须以@qq.com结尾
        if (!str_ends_with($email, '@qq.com')) {
            return $this->error('邮箱格式错误，仅支持QQ邮箱', 4003, [], 400);
        }

        // 校验：该邮箱未被其他已验证会员绑定
        $existing = Customer::where('email', $email)
            ->where('is_verified', true)
            ->where('id', '!=', $id)
            ->first();
        if ($existing) {
            return $this->error('该邮箱已被其他会员绑定', 4005, [], 400);
        }

        $codeKey = self::VERIFY_CODE_PREFIX . $id;
        $limitKey = self::RATE_LIMIT_PREFIX . $id;

        // 防刷：60秒内不能重复发送
        if (Redis::exists($limitKey)) {
            $retryAfter = Redis::ttl($limitKey);
            return $this->error('发送过于频繁，请60秒后重试', 4004, [
                'retry_after' => $retryAfter,
            ]);
        }

        // 如果之前已有验证码，先删除旧的
        if (Redis::exists($codeKey)) {
            Redis::del($codeKey);
        }

        // 生成6位数字验证码
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Redis存储：有效期5分钟
        Redis::setex($codeKey, 300, $code);
        // 频率限制：60秒
        Redis::setex($limitKey, 60, '1');

        try {
            // 发送邮件
            Mail::to($email)->send(new VerificationCodeMail($customer->name, $code, self::SHOP_NAME));

            return $this->success("验证码已发送至 {$email}，5分钟内有效", [
                'email' => $email,
                'expires_in' => 300,
            ]);
        } catch (\Exception $e) {
            // 发送失败，清理Redis
            Redis::del($codeKey);
            Redis::del($limitKey);

            return $this->error('邮件发送失败，请稍后重试', 500, ['error' => $e->getMessage()], 500);
        }
    }

    // ==================== 3. 验证邮箱激活会员 ====================
    /**
     * POST /api/customers/verify-email — 验证邮箱激活会员
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|integer',
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors()->toArray());
        }

        $customerId = $request->customer_id;
        $email = $request->email;
        $code = $request->code;

        // 查询顾客
        $customer = Customer::find($customerId);
        if (!$customer) {
            return $this->error('顾客不存在', 404);
        }

        $codeKey = self::VERIFY_CODE_PREFIX . $customerId;

        // 从Redis读取验证码
        $cachedCode = Redis::get($codeKey);

        // 校验验证码是否存在
        if (!$cachedCode) {
            return $this->error('验证码已过期，请重新获取', 4002, [], 400);
        }

        // 校验验证码是否正确
        if ($cachedCode !== $code) {
            return $this->error('验证码错误或已过期', 4002, [], 400);
        }

        // ✅ 验证通过，立即删除Redis（用完即失效）
        Redis::del($codeKey);
        Redis::del(self::RATE_LIMIT_PREFIX . $customerId);

        // 更新顾客为会员
        $customer->update([
            'email' => $email,
            'type' => 'member',
            'is_verified' => true,
            'became_member_at' => now(),
        ]);

        // 计算下一等级信息
        $nextLevel = $this->getNextLevelInfo($customer->total_spent);

        return $this->success('邮箱验证成功，您已成为正式会员！', [
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'type' => $customer->type,
            'level' => $customer->level,
            'discount_info' => $this->getDiscountInfo($customer->level),
            'next_level' => $nextLevel,
        ]);
    }

    // ==================== 4. 查看顾客详情 ====================
    /**
     * GET /api/customers — 查看顾客详情（通过手机号查询）
     */
    public function show(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('请输入手机号', 422, $validator->errors()->toArray(), 422);
        }

        $phone = $request->input('phone');

        $customer = Customer::with(['firstStore', 'orders' => function ($query) {
            $query->latest()->take(5)->with('items');
        }])->where('phone', $phone)->first();

        if (!$customer) {
            return $this->error('顾客不存在', 404, [], 404);
        }

        // 格式化最近订单
        $recentOrders = $customer->orders->map(function ($order) {
            return [
                'order_no' => $order->order_no,
                'final_amount' => $order->final_amount,
                'items' => $order->items->map(function ($item) {
                    return "{$item->product_name}({$item->spec_name}) × {$item->quantity}";
                })->toArray(),
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return $this->success('查询成功', [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'type' => $customer->type,
            'level' => $customer->level,
            'is_verified' => $customer->is_verified,
            'discount_rate' => $customer->discount_rate,
            'total_spent' => $customer->total_spent,
            'order_count' => $customer->order_count,
            'last_order_at' => $customer->last_order_at?->format('Y-m-d H:i:s'),
            'first_store' => $customer->firstStore?->name,
            'recent_orders' => $recentOrders,
        ]);
    }

    // ==================== 私有辅助方法 ====================

    /**
     * 获取折扣信息描述
     */
    private function getDiscountInfo(string $level): string
    {
        return match ($level) {
            'diamond' => '钻石卡会员，享受85折优惠',
            'gold' => '金卡会员，享受9折优惠',
            'silver' => '银卡会员，享受95折优惠',
            default => '暂无折扣，累计消费满100元升银卡（95折）',
        };
    }

    /**
     * 获取下一等级信息
     */
    private function getNextLevelInfo(float $totalSpent): array
    {
        if ($totalSpent < 100) {
            return [
                'name' => '银卡',
                'need_amount' => number_format(100 - $totalSpent, 2),
                'discount' => '95折',
            ];
        } elseif ($totalSpent < 500) {
            return [
                'name' => '金卡',
                'need_amount' => number_format(500 - $totalSpent, 2),
                'discount' => '9折',
            ];
        } elseif ($totalSpent < 1000) {
            return [
                'name' => '钻石卡',
                'need_amount' => number_format(1000 - $totalSpent, 2),
                'discount' => '85折',
            ];
        }
        return [
            'name' => '已达最高等级',
            'need_amount' => '0.00',
            'discount' => '85折',
        ];
    }

    /**
     * 成功响应
     */
    private function success(string $message, array $data = []): JsonResponse
    {
        return response()->json([
            'code' => 200,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * 错误响应
     * 
     * @param string $message 错误信息
     * @param int $businessCode 业务错误码 (4001, 4002, 4003, 4004, 4005)
     * @param array $data 额外数据
     * @param int $httpStatus HTTP状态码 (默认400)
     */
    private function error(
        string $message, 
        int $businessCode = 4001, 
        array $data = [], 
        int $httpStatus = 400
    ): JsonResponse {
        return response()->json([
            'code' => $businessCode,
            'message' => $message,
            'data' => $data ?: null,
        ], $httpStatus);
    }
}