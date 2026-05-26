<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Material;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductMaterial;
use App\Models\ProductSku;
use App\Models\StockChangeLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Cache;

class FmyController
{
    public function login(): JsonResponse
    {
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
                'code' => 4001,
                'message' => '参数验证失败',
                'data' => null,
                'errors' => $validator->errors()->first(),
            ], 400);
        }
<<<<<<< Updated upstream

        $credentials = request()->only('name', 'password');
=======
        //从当前用户的请求中，精准提取出 name 和 password 这两个字段的值
        $credentials = (object) request()->only('name', 'password');
>>>>>>> Stashed changes

        $employee = Employee::where('name', $credentials->name)->first();

        if (!$employee) {
            return response()->json([
                'code' => 4010,
                'message' => '用户不存在，请检查姓名是否正确',
                'data' => null,
            ], 401);
        }

        if ($employee->status !== 'active') {
            return response()->json([
                'code' => 4030,
                'message' => '该账号已被禁用，请联系管理员',
                'data' => null,
            ], 403);
        }

        if (!Hash::check($credentials->password, $employee->password)) {
            return response()->json([
                'code' => 4010,
                'message' => '密码错误，请重新输入',
                'data' => null,
            ], 401);
        }

        $this->invalidateOldTokens($employee->id);

        $token = JWTAuth::fromUser($employee);

<<<<<<< Updated upstream
        Cache::put('employee_token:' . $employee->id, $token, config('jwt.ttl'));
=======
        //将该员工本次登录生成的全新 Token 存入缓存，使用分钟为单位
        $ttlMinutes = config('jwt.ttl', 10080); // 7天
        Cache::put('employee_token:' . $employee->id, $token, now()->addMinutes($ttlMinutes));
>>>>>>> Stashed changes

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
                    'store_name' => $employee->store?->name ?? '',
                ],
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        try {
            $token = JWTAuth::getToken();
            
            if (!$token) {
                return response()->json([
                    'code' => 4010,
                    'message' => '未提供令牌',
                    'data' => null,
                ], 401);
            }

            try {
                $user = JWTAuth::authenticate();
                
                if ($user) {
                    Cache::forget('employee_token:' . $user->id);
                }
            } catch (\Exception $e) {
                // 即使 authenticate 失败，也继续尝试使 token 失效
            }
            
            JWTAuth::invalidate($token);

            return response()->json([
                'code' => 200,
                'message' => '登出成功',
                'data' => null,
            ]);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'code' => 4010,
                'message' => '令牌无效',
                'data' => null,
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'code' => 4011,
                'message' => '令牌已过期，请重新登录',
                'data' => null,
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 4010,
                'message' => '登出失败：' . $e->getMessage(),
                'data' => null,
            ], 401);
        }
    }

    public function createOrder(): JsonResponse
    {
        $validator = Validator::make(request()->all(), [
            'customer_id' => 'required|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_sku_id' => 'required|exists:product_skus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'activity_id' => 'nullable|exists:activities,id',
            'remark' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 4001,
                'message' => '参数错误',
                'data' => null,
                'errors' => $validator->errors(),
            ], 400);
        }

        $user = JWTAuth::authenticate();
        if (!$user) {
            return response()->json([
                'code' => 4010,
                'message' => '未登录或令牌已过期',
                'data' => null,
            ], 401);
        }

        $customer = Customer::find(request()->input('customer_id'));
        $items = request()->input('items');
        $activityId = request()->input('activity_id');
        $remark = request()->input('remark');

        return DB::transaction(function () use ($user, $customer, $items, $activityId, $remark) {

            $orderItems = [];
            $originalAmount = 0;

            foreach ($items as $item) {
                $sku = ProductSku::with('product')->find($item['product_sku_id']);

                if (!$sku || $sku->status !== 'active') {
                    throw new \Exception("商品SKU不存在或已下架");
                }

                $unitPrice = $sku->price;
                $subtotal = $unitPrice * $item['quantity'];
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

            $activityDiscount = 0;
            $activityDesc = '';
            $discountType = '';

            if ($activityId) {
                $activity = Activity::find($activityId);

                if (!$activity || $activity->status !== 'active') {
                    throw new \Exception("活动不存在或已失效");
                }

                $now = now();
                if ($now->lt($activity->start_date) || $now->gt($activity->end_date)) {
                    throw new \Exception("活动不在有效期内");
                }

                if ($activity->type === 'full_reduction') {
                    if ($originalAmount >= $activity->condition_amount) {
                        $activityDiscount = $activity->benefit_amount;
                        $activityDesc = "满{$activity->condition_amount}减{$activity->benefit_amount}";
                        $discountType = 'activity';
                    }
                } elseif ($activity->type === 'second_half') {
                    if (count($items) >= 2) {
                        $minPriceItem = min($orderItems, fn($a, $b) => $a['unit_price'] <=> $b['unit_price']);
                        $activityDiscount = $minPriceItem['unit_price'] / 2;
                        $activityDesc = "第二杯半价";
                        $discountType = 'activity';
                    }
                }
            }

            $memberDiscountRate = $customer->discount_rate;
            $memberDiscountAmount = $originalAmount * (1 - $memberDiscountRate);
            $memberDiscountDesc = "{$customer->level_name}{$memberDiscountRate}折";

            if ($memberDiscountAmount < $activityDiscount || $activityDiscount === 0) {
                $finalAmount = $originalAmount * $memberDiscountRate;
                $discountAmount = $memberDiscountAmount;
                $discountDesc = $memberDiscountDesc;
                $discountType = 'member';
            } else {
                $finalAmount = $originalAmount - $activityDiscount;
                $discountAmount = $activityDiscount;
                $discountDesc = $activityDesc;
            }

            $finalAmount = round($finalAmount, 2);
            $discountAmount = round($discountAmount, 2);

            $shortage = $this->checkStockAvailability($orderItems);

            if (!empty($shortage)) {
                return response()->json([
                    'code' => 4220,
                    'message' => '库存不足',
                    'data' => [
                        'shortage' => $shortage,
                    ],
                ], 422);
            }

            $this->deductStock($orderItems, $user);

            $orderNo = 'O' . now()->format('YmdHis') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

            $order = Order::create([
                'order_no' => $orderNo,
                'store_id' => $user->store_id,
                'customer_id' => $customer->id,
                'original_amount' => $originalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'activity_id' => $activityId,
                'discount_desc' => $discountDesc,
                'customer_level_at_order' => $customer->level,
                'member_discount_rate' => $memberDiscountRate,
                'status' => 'pending',
                'remark' => $remark,
            ]);

            foreach ($orderItems as $item) {
                OrderItem::create(array_merge($item, ['order_id' => $order->id]));
            }

            $oldLevel = $customer->level;
            $customer->increment('total_spent', $finalAmount);
            $customer->increment('order_count');
            $customer->update(['last_order_at' => now()]);

            if (!$customer->first_store_id) {
                $customer->update(['first_store_id' => $user->store_id]);
            }

            $newLevel = $this->calculateMemberLevel($customer->total_spent);
            if ($newLevel !== $oldLevel) {
                $customer->update(['level' => $newLevel]);
                $memberUpgraded = true;
            } else {
                $memberUpgraded = false;
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
                        'original_amount' => number_format($originalAmount, 2),
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
                    'created_at' => $order->created_at->toIso8601String(),
                ],
            ]);
        });
    }

    public function getOrderDetail($id): JsonResponse
    {
        $order = Order::with(['customer', 'store', 'items', 'activity'])->find($id);

        if (!$order) {
            return response()->json([
                'code' => 4040,
                'message' => '订单不存在',
                'data' => null,
            ], 404);
        }

        $customer = $order->customer;
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

    private function getSpecNameFromIds(string $specIds): string
    {
        if (empty($specIds)) {
            return '';
        }

        $ids = explode(',', $specIds);
        $specs = ProductSpec::whereIn('id', $ids)->pluck('name')->toArray();

        return implode('+', $specs);
    }

    private function checkStockAvailability(array $orderItems): array
    {
        $shortage = [];

        foreach ($orderItems as $item) {
            $materials = ProductMaterial::with('material')
                ->where('product_sku_id', $item['product_sku_id'])
                ->get();

            foreach ($materials as $material) {
                $neededQty = $material->quantity * $item['quantity'];
                $currentStock = $material->material->stock;

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

    private function deductStock(array $orderItems, $user): void
    {
        foreach ($orderItems as $item) {
            $materials = ProductMaterial::with('material')
                ->where('product_sku_id', $item['product_sku_id'])
                ->get();

            foreach ($materials as $material) {
                $deductQty = $material->quantity * $item['quantity'];
                $material->material->decrement('stock', $deductQty);

                StockChangeLog::create([
                    'ingredient_id' => $material->material_id,
                    'store_id' => $user->store_id,
                    'type' => 'order_deduction',
                    'quantity' => -$deductQty,
                    'stock_before' => $material->material->stock + $deductQty,
                    'stock_after' => $material->material->stock,
                    'submitter_id' => $user->id,
                    'reason' => "订单扣减：{$item['product_name']} × {$item['quantity']}",
                ]);
            }
        }
    }

    private function calculateMemberLevel(float $totalSpent): string
    {
        // 会员等级：银卡(0) -> 金卡(2000) -> 钻石卡(4000)
        if ($totalSpent >= 4000) {
            return 'diamond';
        } elseif ($totalSpent >= 2000) {
            return 'gold';
        } else {
            return 'silver'; // 新会员默认为银卡
        }
    }

    private function invalidateOldTokens(int $employeeId): void
    {
        $oldToken = Cache::get('employee_token:' . $employeeId);
        
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

        // 如果新增的是店长，同步更新门店的 manager_id
        if ($data['role'] === 'manager') {
            \App\Models\Store::where('id', $targetStoreId)->update(['manager_id' => $employee->id]);
        }

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
