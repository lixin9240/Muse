<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductMaterial;
use App\Models\ProductSku;
use App\Models\ProductSpec;
use App\Models\StockChangeLog;
use App\Http\Requests\CreateOrderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class FmyController
{
    public function login(): JsonResponse
    {
        //request()->all()：获取数据
        $validator = Validator::make(request()->all(), [
            'name' => 'required|string|max:20',
            'password' => 'required|string|min:6|max:32',
        ], [
            'name.required' => '请输入姓名',
            'name.string' => '姓名必须是字符串',
            'name.max' => '姓名不能超过20个字符',
            'password.required' => '请输入密码',
            'password.string' => '密码必须是字符串',
            'password.min' => '密码长度至少6个字符',
            'password.max' => '密码长度不能超过32个字符',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 400,
                'message' => '参数验证失败',
                'data' => null,
                'errors' => $validator->errors()->first(),
            ], 400);
        }
        //从当前用户的请求中，精准提取出 name 和 password 这两个字段的值
        $credentials = request()->only('name', 'password');

        $employee = Employee::where('name', $credentials['name'])->first();

        if (!$employee) {
            return response()->json([
                'code' => 401,
                'message' => '用户不存在，请检查姓名是否正确',
                'data' => null,
            ], 401);
        }

        if ($employee->status !== 'active') {
            return response()->json([
                'code' => 403,
                'message' => '该账号已被禁用，请联系管理员',
                'data' => null,
            ], 403);
        }

        if (!Hash::check($credentials['password'], $employee->password)) {
            return response()->json([
                'code' => 401,
                'message' => '密码错误，请重新输入',
                'data' => null,
            ], 401);
        }

        //在员工登录成功时，强制让该员工在其他设备上已登录的旧令牌（Token）全部失效
        $this->invalidateOldTokens($employee->id);

        //根据传入的 $employee 用户对象，生成并返回一个全新的 JWT 字符串（Token）。
        $token = JWTAuth::fromUser($employee);

        //将该员工本次登录生成的全新 Token 存入缓存，使用分钟为单位
        $ttlMinutes = config('jwt.ttl', 60);
        Cache::put('employee_token:' . $employee->id, $token, now()->addMinutes($ttlMinutes));

        return response()->json([
            'code' => 200,
            'message' => '登录成功',
            'data' => [
                'token' => $token,
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'role' => $employee->role,
                    'store_id' => $employee->store_id,
                    //$employee->store?->name（可选链操作符 ?->），先看 $employee->store 这个关联对象是否存在
                    //如果存在，就继续往后读取 ->name 属性，如果不存在，它会立刻停止并直接返回 null
                    'store_name' => $employee->store?->name ?? '',
                ],
            ],
        ]);
    }

    private function invalidateOldTokens(int $employeeId): void
    {    //获取旧 Token
        //生成了一个针对当前员工的唯一标识（employee_token:1001），确保系统能精准定位到该员工的数据。
        $oldToken = Cache::get('employee_token:' . $employeeId);


        //setToken是JWT认证库中的一个核心方法，它的作用是将指定的Token字符串手动加载到 JWT 的处理对象中
        //invalidate是JWT认证库中的另一个核心方法，在 JWT的实际业务中，它的核心作用是将指定的 Token 加入黑名单，使其立即失效。
        if ($oldToken) {
            try {
                // 使旧的 JWT Token 失效（加入黑名单）
                JWTAuth::setToken($oldToken)->invalidate();
            } catch (\Exception $e) {
                // 如果旧 token 已经失效或无效，忽略异常
            }

            // 清除缓存中的旧 token 记录
            Cache::forget('employee_token:' . $employeeId);
        }
    }
    public function logout(): JsonResponse
    {
        try {
            // 通过 auth 获取当前已认证的用户（由 auth:employee 中间件保证）
            $user = auth('employee')->user();

            // 使当前 token 失效（从请求头获取）
            JWTAuth::invalidate(JWTAuth::getToken());
            Cache::forget('employee_token:' . $user->id); // 清理缓存

            return response()->json([
                'code' => 200,
                'message' => '登出成功',
                'data' => null,
            ]);

        } catch (TokenInvalidException $e) {
            // 捕获令牌无效异常（如被篡改、格式错误）
            return response()->json([
                'code' => 401,
                'message' => '令牌无效',
                'data' => null,
            ], 401);

        } catch (TokenExpiredException $e) {
            // 捕获令牌过期异常
            return response()->json([
                'code' => 401,
                'message' => '令牌已过期，请重新登录',
                'data' => null,
            ], 401);

        } catch (TokenBlacklistedException $e) {
            // 令牌已在黑名单中，清理缓存并返回登出成功
            Cache::forget('employee_token:' . auth('employee')->id());
            return response()->json([
                'code' => 200,
                'message' => '登出成功',
                'data' => null,
            ]);

        } catch (JWTException $e) {
            // 捕获其他 JWT 相关的异常（如解析失败等）
            return response()->json([
                'code' => 401,
                'message' => '登出失败：' . $e->getMessage(),
                'data' => null,
            ], 401);
        }
    }

    //$request：依赖注入与对象实例
    //把已经通过验证的 CreateOrderRequest 对象，赋值给 $request 变量，传入方法中。
    public function createOrder(CreateOrderRequest $request): JsonResponse
    {
        //获取当前已登录的“员工（employee）”用户信息。
        $user = auth('employee')->user();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '无法创建订单：您尚未登录或登录状态已过期，请先登录',
                'data' => null,
            ], 401);
        }

        //根据传过来的 customer_id，去 customers 数据表中查找并返回对应的客户信息。
        $customer = Customer::find($request->input('customer_id'));
        //从当前的请求数据中，提取出名为 items 的字段值，并赋值给变量 $items。
        $items = $request->input('items');
        $activityId = $request->input('activity_id');
        $remark = $request->input('remark');

        //开启一个数据库事务，并将当前作用域内的 $user、$customer 等外部变量引入到事务闭包（匿名函数）内部去使用。
        return DB::transaction(function () use ($user, $customer, $items, $activityId, $remark) {

            //为接下来的订单处理逻辑准备两个空的“容器
            $orderItems = [];//用来在稍后的循环中，收集并暂存所有即将要批量写入数据库的“订单商品明细”数据。
            $originalAmount = 0;//准备一个初始值为 0 的变量。用来在遍历商品时，把每个商品的（单价 × 数量）不断累加起来，最终算出订单的原始总金额。

            //$items as $item：开始遍历 $items 数组中的每一个商品。
            foreach ($items as $item) {
                //with('product') ：“在查 SKU 的时候，把这个 SKU 所属的 product（商品模型）也提前加载好”。这能有效避免著名的 N+1 查询问题
                $sku = ProductSku::with('product')->find($item['product_sku_id']);

                if (!$sku || $sku->status !== 'active') {
                    throw new \Exception("商品SKU不存在或已下架");
                }

                $unitPrice = $sku->price;
                //算出当前这一种商品在购物车里一共多少钱，应的是订单明细表（Order Items）中的字段subtotal
                $subtotal = $unitPrice * $item['quantity'];
                //累加订单原始总金额，代表的是整个订单在没有任何优惠、运费之前的商品总价。
                $originalAmount += $subtotal;

                $orderItems[] = [
                    'product_sku_id' => $sku->id,
                    'product_name' => $sku->product->name,
                    'spec_name' => $this->getSpecNameFromIds($sku->spec_ids),
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            }

            $activityDiscount = 0;//用来记录优惠了多少钱，默认初始化为 0（即没有优惠）
            $activityDesc = '';//用来记录优惠活动的名称

            if ($activityId) {
                $activity = Activity::find($activityId);

                if (!$activity || $activity->status !== 'active') {
                    throw new \Exception("活动不存在或已失效");
                }

                $now = now();
                //lt 是英文 less than（小于）
                //gt 是英文 greater than（大于）
                if ($now->lt($activity->start_date) || $now->gt($activity->end_date)) {
                    throw new \Exception("活动不在有效期内");
                }

                if ($activity->type === 'full_reduction') {
                    if ($originalAmount >= $activity->condition_amount) { //判断订单的原始总金额（$originalAmount）是否达到了活动要求的门槛金额
                        $activityDiscount = $activity->benefit_amount;//将优惠金额（$activityDiscount）直接设置为活动预设的减免金额
                        $activityDesc = "满{$activity->condition_amount}减{$activity->benefit_amount}";
                    }
                } elseif ($activity->type === 'second_half') {
                    if (count($items) >= 2) {
                        //找出最便宜的商品
                        //fn($a, $b):在排序或比较时，程序会自动从 $orderItems 里每次拿出两个商品，分别赋值给 $a 和 $b
                        //<=>:如果 $a 的单价 小于 $b 的单价，返回 -1（表示 $ a 更小，应该排前面）。
                        //如果两者的单价 相等，返回 0（表示一样大）。
                        //如果 $a 的单价 大于 $b 的单价，返回 1（表示 $ a 更大，应该排后面）。
                        $minPriceItem = min($orderItems, fn($a, $b) => $a['unit_price'] <=> $b['unit_price']);
                        $activityDiscount = $minPriceItem['unit_price'] / 2;
                        $activityDesc = "第二杯半价";
                    }
                }
            }

            $memberDiscountRate = $customer->discount_rate;//取出该会员等级对应的折扣率
            $memberDiscountAmount = $originalAmount * (1 - $memberDiscountRate);
            $memberDiscountDesc = "{$customer->level_name}{$memberDiscountRate}折";

            // 自动选择最优优惠：取会员折扣和活动折扣中优惠金额更大的
            if ($activityDiscount > 0 && $activityDiscount >= $memberDiscountAmount) {
                $finalAmount = $originalAmount - $activityDiscount;
                $discountAmount = $activityDiscount;
                $discountDesc = $activityDesc;
                $discountType = 'activity';
            } else {
                $finalAmount = $originalAmount * $memberDiscountRate;
                $discountAmount = $memberDiscountAmount;
                $discountDesc = $memberDiscountDesc;
                $discountType = 'member';
            }

            //保留两位小数
            $finalAmount = round($finalAmount, 2);//最后实付金额
            $discountAmount = round($discountAmount, 2);//优惠金额

            $shortage = $this->checkStockAvailability($orderItems);

            if (!empty($shortage)) {
                return response()->json([
                    'code' => 422,
                    'message' => '库存不足',
                    'data' => [
                        'shortage' => $shortage,
                    ],
                ], 422);
            }

            //固定前缀 + 精确到秒的时间戳 + 3位随机数
            $orderNo = 'O' . now()->format('YmdHis') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

            $order = Order::create([
                'order_no' => $orderNo,
                'store_id' => $user->store_id,
                'customer_id' => $customer->id,
                'original_amount' => $originalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'activity_id' => $activityId,
                'discount_desc' => $discountDesc,//优惠说明
                'customer_level_at_order' => $customer->level,
                'member_discount_rate' => $memberDiscountRate,
                'status' => 'pending',
                'remark' => $remark,
            ]);

            foreach ($orderItems as $item) {
                OrderItem::create(array_merge($item, ['order_id' => $order->id]));
            }

            $this->deductStock($orderItems, $user, $order);

            $oldLevel = $customer->level;
            $customer->increment('total_spent', $finalAmount);
            $customer->increment('order_count');//将客户的 order_count（累计下单次数）字段直接加 1
            $customer->update(['last_order_at' => now()]);

            //记录用户的首单门店（首次归属）
            if (!$customer->first_store_id) {
                $customer->update(['first_store_id' => $user->store_id]);
            }

            // 只有已验证的会员才能升级等级
            $memberUpgraded = false;
            $upgradeMessage = '';
            if ($customer->is_verified) {
                $newLevel = $this->calculateMemberLevel($customer->total_spent);
                if ($newLevel !== $oldLevel) {
                    $oldLevelName = $customer->level_name;
                    $customer->update(['level' => $newLevel]);
                    $memberUpgraded = true;
                    $upgradeMessage = "恭喜！您的会员等级已从{$oldLevelName}升级为{$customer->level_name}！";
                }
            }

            return response()->json([
                'code' => 200,
                'message' => '订单创建成功',
                'data' => [
                    'order_no' => $order->order_no,
                    'store_name' => $user->store?->name ?? '',
                    'customer' => [
                        'name' => $customer->name,
                        'phone' => $customer->phone,
                        'level' => $customer->level,
                    ],
                    'items' => array_map(fn($item) => [
                        'product_name' => $item['product_name'],
                        'spec_name' => $item['spec_name'],
                        'quantity' => $item['quantity'],
                        'unit_price' => number_format($item['unit_price'], 2),
                        'subtotal' => number_format($item['subtotal'], 2),
                    ], $orderItems),
                    'price_breakdown' => [
                        'original_amount' => number_format($originalAmount, 2),//将商品的单价格式化为保留两位小数的字符串
                        'discount_amount' => number_format($discountAmount, 2),
                        'final_amount' => number_format($finalAmount, 2),
                        'discount_type' => $discountType,
                        'discount_desc' => $discountDesc,
                    ],
                    'member_snapshot' => [
                        'level_at_order' => $customer->level,
                        'discount_rate' => number_format($memberDiscountRate, 2),
                    ],
                    'stock_deducted' => true,
                    'member_upgraded' => $memberUpgraded,
                    'upgrade_message' => $upgradeMessage,
                    'created_at' => $order->created_at->toIso8601String(),
                ],
            ]);
        });
    }



    //(string $specIds)：表示这个方法接收一个名为 $specIds 的参数，并且强制要求它必须是字符串类型
    private function getSpecNameFromIds(string $specIds): string
    {
        if (empty($specIds)) {
            return '';
        }

        //假设传入的 $specIds 是 "2001,3002"，这行代码执行后，$ids 就会变成一个数组：['2001', '3002']。
        //explode：字符串“打散”成数组
        $ids = explode(',', $specIds);
        //从查出来的结果中，只把 name提取出来
        $specs = ProductSpec::whereIn('id', $ids)->pluck('name')->toArray();//去数据库里批量查询并提取名称。

        //implode:将一个数组的所有元素，用指定的符号“拼接”成一个完整的字符串。
        return implode('+', $specs);
    }


    private function checkStockAvailability(array $orderItems): array
    {
        $shortage = [];

        foreach ($orderItems as $item) {
            $materials = ProductMaterial::with('material')
                ->where('product_sku_id', $item['product_sku_id'])//找出当前遍历到的这个商品（SKU），到底需要消耗哪些原材料。
                ->get();

            foreach ($materials as $material) {
                $neededQty = $material->quantity * $item['quantity'];
                $currentStock = $material->material->stock;

                //unit:单位
                if ($currentStock < $neededQty) {
                    $shortage[] = [
                        'material' => $material->material->name,
                        'need' => "{$neededQty}{$material->material->unit}",
                        'stock' => "{$currentStock}{$material->material->unit}",
                    ];
                }
            }
        }

        return $shortage;
    }

    //扣减原材料库存并记录流水
    private function deductStock(array $orderItems, $user, Order $order): void
    {
        //遍历订单商品并获取配方
        foreach ($orderItems as $item) {
            $materials = ProductMaterial::with('material')
                ->where('product_sku_id', $item['product_sku_id'])
                ->get();

            foreach ($materials as $material) {
                //$deductQty：计算这笔订单总共需要消耗多少原材料
                $deductQty = $material->quantity * $item['quantity'];
                //执行扣减
                $material->material->decrement('stock', $deductQty);

                StockChangeLog::create([
                    'ingredient_id' => $material->material_id,
                    'store_id' => $user->store_id,
                    'type' => 'order_deduction',
                    'quantity' => -$deductQty,
                    'stock_before' => $material->material->stock + $deductQty,
                    'stock_after' => $material->material->stock,
                    'order_id' => $order->id,
                    'product_id' => $item['product_sku_id'],
                    'status' => 'approved',
                    'submitter_id' => $user->id,
                    'reason' => "订单扣减：{$item['product_name']} × {$item['quantity']}",
                ]);
            }
        }
    }

    public function getOrderDetail($id): JsonResponse
    {
        $order = Order::with(['customer', 'store', 'items', 'activity'])->find($id);

        if (!$order) {
            return response()->json([
                'code' => 404,
                'message' => '订单不存在',
                'data' => null,
            ], 404);
        }

        //提取订单关联客户的手机号，并对其进行脱敏处理（隐藏中间四位
        $customer = $order->customer;//从当前的订单对象（$order）中，取出下单的客户信息
        //条件 ? 结果A : 结果B：如果订单没有关联到客户，就直接返回一个空字符串 ''
        //截取手机号的前3位，截取手机号的后4位
        $maskedPhone = $customer ? substr($customer->phone, 0, 3) . '****' . substr($customer->phone, -4) : '';

        return response()->json([
            'code' => 200,
            'data' => [
                'order_no' => $order->order_no,
                'status' => $order->status,
                'store' => $order->store?->name ?? '',
                'customer' => [
                    'name' => $customer?->name ?? '',
                    'phone' => $maskedPhone,
                    'level' => $customer?->level ?? 'none',
                ],
                'items' => $order->items->map(fn($item) => [
                    'product_name' => $item->product_name,
                    'spec_name' => $item->spec_name,
                    'quantity' => $item->quantity,
                    'unit_price' => number_format($item->unit_price, 2),
                    'subtotal' => number_format($item->subtotal, 2),
                ]),
                'original_amount' => number_format($order->original_amount, 2),
                'discount_amount' => number_format($order->discount_amount, 2),
                'final_amount' => number_format($order->final_amount, 2),
                'discount_desc' => $order->discount_desc,
                'remark' => $order->remark,
                'completed_at' => $order->completed_at?->toIso8601String(),
                'created_at' => $order->created_at->toIso8601String(),
            ],
        ]);
    }




    private function calculateMemberLevel(float $totalSpent): string
    {
        // 会员等级：银卡(验证即得) -> 金卡(500) -> 钻石卡(1000)
        if ($totalSpent >= 1000) {
            return 'diamond'; // 最高等级
        } elseif ($totalSpent >= 500) {
            return 'gold';
        } else {
            return 'silver'; // 验证邮箱后默认银卡
        }
    }

    /**
     * 添加门店员工
     * 权限：经理可添加店长和店员，店长只能添加店员
     */
    public function addEmployee(): JsonResponse
    {
        $user = request()->user('employee');

        // 中英文角色映射表
        $roleMap = [
            '经理' => 'director',
            '店长' => 'manager',
            '店员' => 'staff',
        ];

        // 将前端传来的中文角色转换为英文
        $inputRole = request()->input('role');
        if (isset($roleMap[$inputRole])) {
            request()->merge(['role' => $roleMap[$inputRole]]);
        }

        if ($user->role === 'staff') {
            return response()->json([
                'code' => 403,
                'message' => '权限不足，仅经理或店长可添加员工',
                'data' => null,
            ], 403);
        }

        $allowedRoles = $user->role === 'director' ? ['manager', 'staff'] : ['staff'];
        // 同时支持中文角色名验证
        $allowedRoleNames = array_merge($allowedRoles, array_keys(array_intersect($roleMap, $allowedRoles)));

        $rules = [
            'name' => 'required|string|max:20|unique:employees,name',
            'phone' => 'required|regex:/^1[3-9]\d{9}$/|unique:employees,phone',
            'password' => 'required|string|min:6|max:32',
            'role' => 'required|in:' . implode(',', $allowedRoleNames),
        ];

        if ($user->role === 'director') {
            $rules['store_id'] = 'required|exists:stores,id';
        }

        $validator = Validator::make(request()->all(), $rules, [
            'name.required' => '请输入员工姓名',
            'name.max' => '员工姓名不能超过20个字符',
            'name.unique' => '该员工姓名已被使用',
            'phone.required' => '请输入员工手机号',
            'phone.regex' => '手机号格式不正确，请输入有效的11位手机号码（如13812345678）',
            'phone.unique' => '该手机号已被其他员工使用',
            'password.required' => '请设置登录密码',
            'password.min' => '密码长度至少6个字符',
            'password.max' => '密码长度不能超过32个字符',
            'role.required' => '请选择员工角色',
            'role.in' => '您无权添加该角色',
            'store_id.required' => '请选择所属门店',
            'store_id.exists' => '所选门店不存在',
        ]);

        if ($validator->fails()) {
            $missingFields = [];
            foreach ($validator->errors()->toArray() as $field => $messages) {
                $missingFields[] = $field;
            }
            return response()->json([
                'code' => 400,
                'message' => '参数验证失败：' . $validator->errors()->first(),
                'data' => [
                    'missing_fields' => $missingFields,
                ],
            ], 400);
        }

        $data = $validator->validated();
        $targetStoreId = $user->role === 'director' ? $data['store_id'] : $user->store_id;

        if ($data['role'] === 'manager') {
            $existingManager = Employee::where('store_id', $targetStoreId)
                ->where('role', 'manager')
                ->where('status', 'active')
                ->exists();

            if ($existingManager) {
                return response()->json([
                    'code' => 400,
                    'message' => '该门店已存在店长，每个门店只能有一个店长',
                    'data' => null,
                ], 400);
            }
        }

        $employee = Employee::create([
            'store_id' => $targetStoreId,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'status' => 'active',
        ]);

        return response()->json([
            'code' => 200,
            'message' => '员工添加成功',
            'data' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'phone' => $employee->phone,
                'role' => $employee->role,
                'role_name' => match($employee->role) {
                    'manager' => '店长',
                    'staff' => '店员',
                    default => $employee->role,
                },
                'store_id' => $employee->store_id,
                'status' => $employee->status,
                'created_at' => $employee->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * 获取门店员工列表
     */
    public function listEmployees(): JsonResponse
    {
        $user = request()->user('employee');

        $query = Employee::where('status', 'active');

        if ($user->role === 'director') {
            if (request()->has('store_id')) {
                $query->where('store_id', request('store_id'));
            }
        } else {
            $query->where('store_id', $user->store_id);
        }

        $employees = $query->get();

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => $employees->map(fn($emp) => [
                'id' => $emp->id,
                'name' => $emp->name,
                'store_id' => $emp->store_id,
            ]),
        ]);
    }

    /**
     * 删除门店员工（软删除）
     */
    public function deleteEmployee(int $employeeId): JsonResponse
    {
        $user = request()->user('employee');

        if ($user->role === 'staff') {
            return response()->json([
                'code' => 403,
                'message' => '权限不足，仅经理或店长可删除员工',
                'data' => null,
            ], 403);
        }

        $query = Employee::where('id', $employeeId);

        if ($user->role !== 'director') {
            $query->where('store_id', $user->store_id);
        }

        $employee = $query->first();

        if (!$employee) {
            return response()->json([
                'code' => 404,
                'message' => '员工不存在',
                'data' => null,
            ], 404);
        }

        if ($user->role === 'manager' && $employee->role !== 'staff') {
            return response()->json([
                'code' => 403,
                'message' => '权限不足，店长只能删除店员',
                'data' => null,
            ], 403);
        }

        if ($employee->id === $user->id) {
            return response()->json([
                'code' => 400,
                'message' => '不能删除自己',
                'data' => null,
            ], 400);
        }

        // 处理关联数据：将员工关联的库存日志的外键置空（保留历史记录，不级联删除）
        $employee->submittedStockLogs()->update(['submitter_id' => null]);
        $employee->approvedStockLogs()->update(['approver_id' => null]);

        // 物理删除员工记录
        $employee->delete();

        $message = '员工已删除';
        if ($employee->role === 'manager') {
            $message .= '，该门店暂无店长，请及时分配新店长';
        }

        return response()->json([
            'code' => 200,
            'message' => $message,
            'data' => null,
        ]);
    }

    public function getEmployeeDetail(int $employeeId): JsonResponse
    {
        $user = request()->user('employee');

        $employee = Employee::find($employeeId);

        if (!$employee) {
            return response()->json([
                'code' => 404,
                'message' => '员工不存在',
                'data' => null,
            ], 404);
        }

        if ($user->role === 'manager' && $user->store_id !== $employee->store_id) {
            return response()->json([
                'code' => 403,
                'message' => '权限不足，只能查看本店员工',
                'data' => null,
            ], 403);
        }

        if ($user->role === 'staff' && $user->id !== $employeeId) {
            return response()->json([
                'code' => 403,
                'message' => '权限不足，只能查看自己的信息',
                'data' => null,
            ], 403);
        }

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'phone' => $employee->phone,
                'role' => $employee->role,
                'role_name' => match($employee->role) {
                    'director' => '经理',
                    'manager' => '店长',
                    'staff' => '店员',
                    default => $employee->role,
                },
                'store_id' => $employee->store_id,
                'status' => $employee->status,
                'created_at' => $employee->created_at->toIso8601String(),
                'updated_at' => $employee->updated_at->toIso8601String(),
            ],
        ]);
    }
}
