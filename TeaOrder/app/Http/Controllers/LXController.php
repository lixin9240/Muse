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

        // 更新顾客为会员（银卡）
        $customer->update([
            'email' => $email,
            'type' => 'member',
            'level' => 'silver',  // 新会员默认为银卡
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

    // ==================== 5. 添加门店 ====================
    /**
     * POST /api/stores — 添加门店
     * 权限：director（经理）
     */
    public function storeStore(Request $request): JsonResponse
    {
        // 检查权限
        $employee = $request->user('employee');
        if (!$employee || $employee->role !== 'director') {
            return $this->error('权限不足，只有经理可以添加门店', 4030, [], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'address' => 'nullable|string|max:200',
            'phone' => 'nullable|string|max:20',
            'manager_name' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors()->toArray(), 422);
        }

        // 创建门店
        $store = \App\Models\Store::create([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'status' => $request->status ?? 'active',
            'manager_id' => null, // 新门店暂时无店长
        ]);

        return $this->success('门店添加成功', [
            'id' => $store->id,
            'name' => $store->name,
            'address' => $store->address,
            'phone' => $store->phone,
            'status' => $store->status,
            'created_at' => $store->created_at->format('Y-m-d H:i:s'),
        ]);
    }

    // ==================== 6. 获取门店列表 ====================
    /**
     * GET /api/stores — 获取门店列表
     * 权限：director（经理查看所有），manager/staff（查看自己所属门店）
     */
    public function indexStore(Request $request): JsonResponse
    {
        $employee = $request->user('employee');
        
        if (!$employee) {
            return $this->error('未登录或令牌已过期', 4010, [], 401);
        }

        $status = $request->input('status');

        // 构建查询
        $query = \App\Models\Store::query();

        // 权限控制
        if ($employee->role === 'director') {
            // 经理可以查看所有门店
        } else {
            // 店长和店员只能查看自己所属的门店
            $query->where('id', $employee->store_id);
        }

        // 状态筛选
        if ($status) {
            $query->where('status', $status);
        }

        $stores = $query->get();

        // 格式化数据
        $storeList = $stores->map(function ($store) {
            return [
                'id' => $store->id,
                'name' => $store->name,
                'address' => $store->address,
                'phone' => $store->phone,
                'status' => $store->status,
                'employee_count' => $store->employees()->count(),
                'today_orders' => $store->orders()->whereDate('created_at', today())->count(),
                'today_amount' => $store->orders()->whereDate('created_at', today())->sum('final_amount'),
            ];
        });

        return $this->success('查询成功', $storeList->toArray());
    }

    // ==================== 7. 更新门店信息 ====================
    /**
     * PUT /api/stores/{id} — 更新门店信息
     * 权限：director（经理）
     */
    public function updateStore(Request $request, int $id): JsonResponse
    {
        // 检查权限
        $employee = $request->user('employee');
        if (!$employee || $employee->role !== 'director') {
            return $this->error('权限不足，只有经理可以更新门店信息', 4030, [], 403);
        }

        $store = \App\Models\Store::find($id);
        if (!$store) {
            return $this->error('门店不存在', 404, [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:200',
            'phone' => 'nullable|string|max:20',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors()->toArray(), 422);
        }

        // 检查传入数据是否与已有数据相同（只对比实际传入且非null的字段）
        $sameFields = [];
        $updateData = [];
        $checkedFields = [];

        // 使用 $request->input() 来判断字段是否真正传入（排除null值）
        if ($request->input('name') !== null) {
            $checkedFields[] = 'name';
            if ($request->name === $store->name) {
                $sameFields[] = 'name';
            } else {
                $updateData['name'] = $request->name;
            }
        }
        if ($request->input('address') !== null) {
            $checkedFields[] = 'address';
            if ($request->address === $store->address) {
                $sameFields[] = 'address';
            } else {
                $updateData['address'] = $request->address;
            }
        }
        if ($request->input('phone') !== null) {
            $checkedFields[] = 'phone';
            if ($request->phone === $store->phone) {
                $sameFields[] = 'phone';
            } else {
                $updateData['phone'] = $request->phone;
            }
        }
        if ($request->input('status') !== null) {
            $checkedFields[] = 'status';
            if ($request->status === $store->status) {
                $sameFields[] = 'status';
            } else {
                $updateData['status'] = $request->status;
            }
        }

        // 如果没有传入任何有效字段
        if (empty($checkedFields)) {
            return $this->error('请传入需要更新的字段', 4007, [], 400);
        }

        // 如果所有传入的字段都与原数据相同
        if (empty($updateData)) {
            return $this->error('传入的数据与现有数据相同，无需更新', 4006, [
                'same_fields' => $sameFields,
                'current_data' => [
                    'name' => $store->name,
                    'address' => $store->address,
                    'phone' => $store->phone,
                    'status' => $store->status,
                ]
            ], 400);
        }

        // 执行更新
        $store->update($updateData);

        $responseData = [
            'id' => $store->id,
            'name' => $store->name,
            'address' => $store->address,
            'phone' => $store->phone,
            'status' => $store->status,
            'updated_at' => $store->updated_at->format('Y-m-d H:i:s'),
        ];

        // 如果有部分字段相同，添加提醒
        if (!empty($sameFields)) {
            $responseData['notice'] = '以下字段值未改变：' . implode('、', $sameFields);
            $responseData['unchanged_fields'] = $sameFields;
        }

        return $this->success('门店信息更新成功', $responseData);
    }

    // ==================== 8. OSS 图片上传 ====================
    /**
     * POST /api/upload/signature — 获取OSS上传签名（前端直传）
     * 权限：manager, director
     * 
     * 前端直传流程：
     * 1. 前端调用此接口获取签名和上传参数
     * 2. 前端直接上传文件到OSS（使用返回的表单参数）
     * 3. OSS回调或前端通知服务端上传完成
     */
    public function getOssSignature(Request $request): JsonResponse
    {
        // 检查权限
        $employee = $request->user('employee');
        if (!$employee || !in_array($employee->role, ['manager', 'director'])) {
            return $this->error('权限不足，只有店长和经理可以上传图片', 4030, [], 403);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'nullable|string|in:product,avatar,store',
            'file_name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors()->toArray(), 422);
        }

        // 图片类型
        $type = $request->input('type', 'product');
        
        // 生成存储路径
        $date = date('Ymd');
        $randomName = uniqid() . '_' . substr(md5(mt_rand()), 0, 8);
        $extension = 'jpg'; // 默认扩展名
        
        // 如果前端传了文件名，尝试获取扩展名
        if ($request->file_name) {
            $ext = pathinfo($request->file_name, PATHINFO_EXTENSION);
            if ($ext && in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp'])) {
                $extension = strtolower($ext);
            }
        }
        
        $fileName = "{$randomName}.{$extension}";
        $dir = "{$type}s/{$date}/";
        $objectKey = $dir . $fileName;

        // OSS 配置
        $accessKeyId = env('OSS_ACCESS_KEY_ID');
        $accessKeySecret = env('OSS_ACCESS_KEY_SECRET');
        $bucket = env('OSS_BUCKET');
        $endpoint = env('OSS_ENDPOINT');
        
        if (!$accessKeyId || !$accessKeySecret || !$bucket || !$endpoint) {
            return $this->error('OSS配置不完整，请联系管理员', 500, [], 500);
        }

        // 构建回调地址（可选）
        $callbackUrl = url('/api/upload/callback');
        $callbackBody = json_encode([
            'filename' => '${object}',
            'size' => '${size}',
            'mimeType' => '${mimeType}',
            'height' => '${imageInfo.height}',
            'width' => '${imageInfo.width}',
        ]);
        $callbackBase64 = base64_encode(json_encode([
            'callbackUrl' => $callbackUrl,
            'callbackBody' => $callbackBody,
            'callbackBodyType' => 'application/json',
        ]));

        // 过期时间（30分钟）
        $expire = 1800;
        $end = time() + $expire;
        $expiration = gmdate('Y-m-d\TH:i:s.000\Z', $end);

        // 构建 Policy
        $conditions = [
            ['content-length-range', 0, 5 * 1024 * 1024], // 最大 5MB
            ['starts-with', '$key', $dir], // 限制上传路径
        ];

        $policy = json_encode([
            'expiration' => $expiration,
            'conditions' => $conditions,
        ]);
        $policyBase64 = base64_encode($policy);

        // 计算签名
        $signature = base64_encode(hash_hmac('sha1', $policyBase64, $accessKeySecret, true));

        // 构建上传URL
        $uploadUrl = "https://{$bucket}.{$endpoint}";
        $cdnDomain = env('OSS_CDN_DOMAIN');
        $fileUrl = $cdnDomain 
            ? "https://{$cdnDomain}/{$objectKey}"
            : "https://{$bucket}.{$endpoint}/{$objectKey}";

        return $this->success('获取上传签名成功', [
            'access_key_id' => $accessKeyId,
            'policy' => $policyBase64,
            'signature' => $signature,
            'upload_url' => $uploadUrl,
            'object_key' => $objectKey,
            'file_url' => $fileUrl,
            'expire_at' => $end,
            'form_data' => [
                'key' => $objectKey,
                'OSSAccessKeyId' => $accessKeyId,
                'policy' => $policyBase64,
                'Signature' => $signature,
                'success_action_status' => '200',
                'callback' => $callbackBase64,
            ],
            'max_size' => 5 * 1024 * 1024, // 5MB
            'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
        ]);
    }

    /**
     * POST /api/upload/callback — OSS上传回调
     * 处理OSS上传完成后的回调通知
     */
    public function ossCallback(Request $request): JsonResponse
    {
        // 验证回调签名（生产环境需要实现）
        // 这里简化处理，实际应该验证OSS的回调签名
        
        $data = $request->all();
        
        // 记录上传日志
        \Illuminate\Support\Facades\Log::info('OSS Upload Callback', $data);
        
        return response()->json([
            'Status' => 'OK',
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
            default => '暂无折扣，验证邮箱成为银卡会员（95折）',
        };
    }

    /**
     * 获取下一等级信息
     * 银卡(0) -> 金卡(2000) -> 钻石卡(4000)
     */
    private function getNextLevelInfo(float $totalSpent): array
    {
        if ($totalSpent < 2000) {
            return [
                'name' => '金卡',
                'need_amount' => number_format(2000 - $totalSpent, 2),
                'discount' => '9折',
            ];
        } elseif ($totalSpent < 4000) {
            return [
                'name' => '钻石卡',
                'need_amount' => number_format(4000 - $totalSpent, 2),
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