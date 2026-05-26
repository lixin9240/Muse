<?php

namespace App\Http\Controllers;

<<<<<<< Updated upstream
use App\Models\{Category, Customer, Material, Order, OrderItem, Product, ProductMaterial, ProductSpec};
=======
use App\Models\{Category, Customer, FileUpload, Material, Order, OrderItem, Product, ProductMaterial, ProductSpec, ProductSku};
>>>>>>> Stashed changes
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Cache, DB, Storage, Validator, Redis};
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class WjcController
{
    // 允许的图片MIME类型白名单
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
    ];

    // 允许的图片扩展名白名单
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    // 最大文件大小 (5MB)
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    // 图片尺寸限制 (宽x高)
    private const MIN_WIDTH = 100;
    private const MIN_HEIGHT = 100;
    private const MAX_WIDTH = 4096;
    private const MAX_HEIGHT = 4096;

    /**
     * 检查产品库存状态
     */
    private function checkProductStockStatus(int $productId): array
    {
        // TODO: Implement stock status checking logic
        return [];
    }

    /**
     * 清理产品图片（减少引用计数，ref_count=0时删除OSS文件）
     */
    private function cleanupProductImage(string $imageUrl): void
    {
        // 根据URL查找文件记录
        $fileUpload = FileUpload::where('file_url', $imageUrl)->first();

        if (!$fileUpload) {
            return;
        }

        // 减少引用计数
        $newRefCount = max(0, $fileUpload->ref_count - 1);
        $fileUpload->update(['ref_count' => $newRefCount]);

        // 如果引用计数为0，删除OSS上的物理文件和数据库记录
        if ($newRefCount === 0) {
            try {
                Storage::disk('oss')->delete($fileUpload->file_path);
                \Illuminate\Support\Facades\Log::info("OSS文件已删除", ['path' => $fileUpload->file_path]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("OSS文件删除失败", [
                    'path' => $fileUpload->file_path,
                    'error' => $e->getMessage()
                ]);
            }

            $fileUpload->delete();
        }
    }

    /**
     * 查看饮品列表（点单用）
     */
    public function index(): JsonResponse
    {
        /** @var int|null $categoryId */
        $categoryId = request()->input('category_id');
        $keyword = request()->input('keyword');
        $groupByCategory = request()->boolean('grouped', false);
        
        // 生成缓存Key（根据参数动态生成）
        $cacheParams = http_build_query([
            'category_id' => $categoryId,
            'keyword' => $keyword,
            'grouped' => $groupByCategory
        ]);
        $cacheKey = "products:menu:" . md5($cacheParams);
        
        // 从Redis缓存读取（5分钟过期）
        $cachedData = Cache::get($cacheKey);
        if ($cachedData !== null) {
            return response()->json([
                'code' => 200,
                'data' => $cachedData,
                '_cached' => true  // 调试用：标识来自缓存
            ]);
        }
        
        // 构建查询
        $query = Product::with(['category', 'specs'])
            ->where('status', 'active')
            ->orderBy('sort_order');
        
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        
        if ($keyword) {
            $query->where('name', 'like', "%{$keyword}%");
        }
        
        $products = $query->get();
        
        // 处理产品数据（含库存状态）
        $productList = $products->map(function ($product) {
            $stockStatus = $this->checkProductStockStatus($product->id);
            
            return [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category->name,
                'image_url' => $product->image_url,
                'description' => $product->description,
                'stock_status' => $stockStatus['status'],      // available | low_stock | out_of_stock
                'stock_info' => $stockStatus['info'],           // 库存详情信息
                'specs' => $product->specs->map(function ($spec) use ($product) {
                    return [
                        'id' => $spec->id,
                        'name' => $spec->name,
                        'price' => number_format($product->base_price + $spec->extra_price, 2)
                    ];
                })
            ];
        });
        
        // 根据参数决定返回格式
        if ($groupByCategory && !$categoryId) {
            // 按分类分组展示
            $responseData = [
                'categories' => $this->groupProductsByCategory($productList),
                'total_count' => $productList->count()
            ];
        } else {
            // 平铺展示（默认）
            $responseData = $productList;
        }
        
        // 存入Redis缓存（5分钟=300秒）
        Cache::put($cacheKey, $responseData, 300);
        
        return response()->json([
            'code' => 200,
            'data' => $responseData,
            '_cached' => false
        ]);
    }
    
    //检查产品库存状态

    /**
     * 检查产品库存状态（通过SKU关联查询）
     */
    private function checkProductStockStatusBySku(int $productId): array
    {
        // 方式1：通过SKU关联查询（推荐，符合当前数据库设计）
        $skuIds = ProductSpec::where('product_id', $productId)->pluck('id');
        
        if ($skuIds->isEmpty()) {
            return ['status' => 'available', 'info' => null];
        }
        
        // 查询该产品所有SKU的配方，并按原料ID分组聚合最大用量
        $materialAggregates = ProductMaterial::with('material')
            ->whereIn('product_sku_id', $skuIds)
            ->whereHas('material', fn($q) => $q->where('materials.status', 'active'))
            ->get()
            ->groupBy('material_id')
            ->map(function ($group) {
                $material = $group->first()->material;
                // 取该原料在所有规格中的最大需求量（最坏情况）
                $maxQuantityNeeded = $group->max('quantity');
                
                return [
                    'material' => $material,
                    'max_quantity_needed' => $maxQuantityNeeded,
                    'can_make_count' => $maxQuantityNeeded > 0 
                        ? floor($material->stock / $maxQuantityNeeded)
                        : 9999
                ];
            });
        
        if ($materialAggregates->isEmpty()) {
            return ['status' => 'available', 'info' => null];
        }
        
        // 找出瓶颈原料（可制作份数最少）
        $bottleneck = $materialAggregates->sortBy('can_make_count')->first();
        $bottleneckMaterial = $bottleneck['material'];
        $canMakeCount = $bottleneck['can_make_count'];
        
        // 判断库存状态
        if ($canMakeCount <= 0) {
            return [
                'status' => 'out_of_stock',
                'info' => [
                    'shortage_material' => $bottleneckMaterial->name,
                    'message' => "{$bottleneckMaterial->name}已耗尽（剩余{$bottleneckMaterial->stock}{$bottleneckMaterial->unit}）"
                ]
            ];
        } elseif ($canMakeCount <= 10) {  // 少于10份视为低库存
            return [
                'status' => 'low_stock',
                'info' => [
                    'shortage_material' => $bottleneckMaterial->name,
                    'remaining_cups' => $canMakeCount,
                    'message' => "仅剩约{$canMakeCount}份（{$bottleneckMaterial->name}不足）"
                ]
            ];
        } else {
            return ['status' => 'available', 'info' => null];
        }
    }
    
    //按分类分组产品
    
    private function groupProductsByCategory($products): array
    {
        $grouped = [];
        
        foreach ($products as $product) {
            $categoryName = $product['category'];
            if (!isset($grouped[$categoryName])) {
                $grouped[$categoryName] = [
                    'name' => $categoryName,
                    'count' => 0,
                    'products' => []
                ];
            }
            $grouped[$categoryName]['count']++;
            $grouped[$categoryName]['products'][] = $product;
        }
        
        return array_values($grouped);
    }

    //添加饮品（管理员）

    public function store(): JsonResponse
    {
        $user = JWTAuth::authenticate();
        if (!$user || $user->role !== 'manager') {
            return response()->json([
                'code' => 4030,
                'message' => '仅管理员可操作'
            ], 403);
        }

        $validator = Validator::make(request()->all(), [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:100',
            'base_price' => 'required|numeric|min:0',
            'image_id' => 'nullable|exists:file_uploads,id',
            'description' => 'nullable|string|max:500',
            'specs' => 'required|array|min:1',
            'specs.*.name' => 'required|string|max:50',
            'specs.*.extra_price' => 'required|numeric|min:0',
            'materials' => 'nullable|array',
            'materials.*.material_id' => 'required_with:materials|exists:materials,id',
            'materials.*.quantity' => 'required_with:materials|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 4001,
                'message' => '参数错误',
                'errors' => $validator->errors()
            ], 400);
        }

        return DB::transaction(function () {
            // 获取图片URL
            $imageUrl = null;
            if (request('image_id')) {
                $fileUpload = \App\Models\FileUpload::find(request('image_id'));
                if ($fileUpload && $fileUpload->status === 'active') {
                    $imageUrl = $fileUpload->file_url;
                    // 标记图片已被使用
                    $fileUpload->update(['ref_count' => $fileUpload->ref_count + 1]);
                }
            }

            $product = Product::create([
                'category_id' => request('category_id'),
                'name' => request('name'),
                'base_price' => request('base_price'),
                'image_url' => $imageUrl,
                'description' => request('description'),
                'status' => 'active',
                'sort_order' => 0
            ]);

            foreach (request('specs') as $index => $spec) {
                ProductSpec::create([
                    'product_id' => $product->id,
                    'name' => $spec['name'],
                    'extra_price' => $spec['extra_price'],
                    'sort_order' => $index
                ]);
            }

            if (request()->has('materials')) {
                foreach (request('materials') as $material) {
                    ProductMaterial::create([
                        'product_sku_id' => $product->specs->first()->id ?? null,
                        'material_id' => $material['material_id'],
                        'quantity' => $material['quantity']
                    ]);
                }
            }

            return response()->json([
                'code' => 200,
                'message' => '饮品添加成功',
                'data' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'status' => $product->status
                ]
            ]);
        });
    }

    /**
     * 查看产品详情
     */
    public function show($id): JsonResponse
    {
        $product = Product::with(['category', 'specs', 'materials.material'])->find($id);

        if (!$product) {
            return response()->json([
                'code' => 404,
                'message' => '产品不存在'
            ], 404);
        }

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => $product
        ]);
    }

    /**
     * 更新产品（管理员）
     */
    public function update($id): JsonResponse
    {
        $user = auth('employee')->user();
        if (!$user || $user->role !== 'manager') {
            return response()->json([
                'code' => 403,
                'message' => '仅管理员可操作'
            ], 403);
        }

        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'code' => 404,
                'message' => '产品不存在'
            ], 404);
        }

        return DB::transaction(function () use ($product) {
            // 如果更新了图片，清理旧图片
            if (request()->has('image_id') && request('image_id') !== $product->image_id) {
                if ($product->image_url) {
                    $this->cleanupProductImage($product->image_url);
                }

                // 关联新图片（增加引用计数）
                if (request('image_id')) {
                    $newFileUpload = FileUpload::find(request('image_id'));
                    if ($newFileUpload && $newFileUpload->status === 'active') {
                        $product->image_url = $newFileUpload->file_url;
                        $newFileUpload->update(['ref_count' => $newFileUpload->ref_count + 1]);
                    }
                } else {
                    $product->image_url = null;
                }
            }

            // 更新其他字段
            $updateData = request()->only(['name', 'base_price', 'description', 'category_id', 'status']);
            $product->update(array_filter($updateData));

            // TODO: 更新规格和材料信息

            return response()->json([
                'code' => 200,
                'message' => '更新成功',
                'data' => $product->fresh(['category', 'specs'])
            ]);
        });
    }

    /**
     * 删除产品（管理员）
     * 同时清理关联的OSS文件
     */
    public function destroy($id): JsonResponse
    {
        $user = auth('employee')->user();
        if (!$user || $user->role !== 'manager') {
            return response()->json([
                'code' => 403,
                'message' => '仅管理员可操作'
            ], 403);
        }

        $product = Product::with(['specs', 'skus.materials'])->find($id);
        if (!$product) {
            return response()->json([
                'code' => 404,
                'message' => '产品不存在'
            ], 404);
        }

        return DB::transaction(function () use ($product) {
            // 1. 清理关联的图片（减少引用计数，必要时删除OSS文件）
            if ($product->image_url) {
                $this->cleanupProductImage($product->image_url);
            }

            // 2. 删除产品规格
            foreach ($product->specs as $spec) {
                $spec->delete();
            }

            // 3. 删除SKU及材料关联
            foreach ($product->skus as $sku) {
                $sku->materials()->delete();
                $sku->delete();
            }

            // 4. 删除产品本身（软删除）
            $product->delete();

            return response()->json([
                'code' => 200,
                'message' => '删除成功',
                'data' => [
                    'product_id' => $product->id,
                    'oss_files_cleaned' => true
                ]
            ]);
        });
    }

    //今日营业数据

    public function today(): JsonResponse
    {
        $user = JWTAuth::authenticate();
        if (!$user) {
            return response()->json(['code' => 4010, 'message' => '未登录'], 401);
        }

        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');
        $storeId = $user->store_id;

        $todayOrders = Order::with('items')
            ->where('store_id', $storeId)
            ->whereDate('created_at', $today)
            ->whereIn('status', ['completed', 'making'])
            ->get();

        $yesterdayOrders = Order::with('items')
            ->where('store_id', $storeId)
            ->whereDate('created_at', $yesterday)
            ->whereIn('status', ['completed', 'making'])
            ->get();

        $totalAmount = $todayOrders->sum('final_amount');
        $totalOrders = $todayOrders->count();
        $totalCups = $todayOrders->sum(fn($order) => $order->items->sum('quantity'));
        $avgOrderValue = $totalOrders > 0 ? round($totalAmount / $totalOrders, 2) : 0;

        $yesterdayAmount = $yesterdayOrders->sum('final_amount');
        $yesterdayOrdersCount = $yesterdayOrders->count();
        $yesterdayCups = $yesterdayOrders->sum(fn($order) => $order->items->sum('quantity'));

        $compareAmount = $yesterdayAmount > 0 ? round(($totalAmount - $yesterdayAmount) / $yesterdayAmount, 2) : 0;
        $compareOrders = $yesterdayOrdersCount > 0 ? round(($totalOrders - $yesterdayOrdersCount) / $yesterdayOrdersCount, 2) : 0;
        $compareCups = $yesterdayCups > 0 ? round(($totalCups - $yesterdayCups) / $yesterdayCups, 2) : 0;

        $hourlyTrend = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->select(
                DB::raw('HOUR(orders.created_at) as hour'),
                DB::raw('SUM(orders.final_amount) as amount'),
                DB::raw('COUNT(DISTINCT orders.id) as orders')
            )
            ->where('orders.store_id', $storeId)
            ->whereDate('orders.created_at', $today)
            ->whereIn('orders.status', ['completed', 'making'])
            ->groupBy(DB::raw('HOUR(orders.created_at)'))
            ->orderBy('hour')
            ->get()
            ->map(fn($item) => [
                'hour' => str_pad($item->hour, 2, '0', STR_PAD_LEFT),
                'amount' => (float) $item->amount,
                'orders' => $item->orders
            ]);

        return response()->json([
            'code' => 200,
            'data' => [
                'overview' => [
                    'total_amount' => number_format($totalAmount, 2),
                    'total_orders' => $totalOrders,
                    'total_cups' => $totalCups,
                    'avg_order_value' => number_format($avgOrderValue, 2),
                    'compare_yesterday' => [
                        'amount' => $compareAmount,
                        'orders' => $compareOrders,
                        'cups' => $compareCups
                    ]
                ],
                'hourly_trend' => $hourlyTrend
            ]
        ]);
    }

    //销量排行榜

    public function ranking(): JsonResponse
    {
        $user = JWTAuth::authenticate();
        if (!$user) {
            return response()->json(['code' => 4010, 'message' => '未登录'], 401);
        }

        $startDate = request('start_date', now()->firstOfMonth()->format('Y-m-d'));
        $endDate = request('end_date', now()->format('Y-m-d'));
        $storeId = request('store_id', $user->store_id);

        $topProducts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->select(
                'order_items.product_name',
                DB::raw('SUM(order_items.quantity) as sold_count'),
                DB::raw('SUM(order_items.subtotal) as revenue')
            )
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($storeId, fn($q) => $q->where('orders.store_id', $storeId))
            ->whereIn('orders.status', ['completed', 'making'])
            ->groupBy('order_items.product_name')
            ->orderByDesc('sold_count')
            ->limit(5)
            ->get()
            ->map(function ($item, $index) {
                $totalRevenue = DB::table('order_items')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->whereBetween('orders.created_at', [request('start_date', now()->firstOfMonth()), request('end_date', now())])
                    ->whereIn('orders.status', ['completed', 'making'])
                    ->sum('order_items.subtotal');
                
                return [
                    'rank' => $index + 1,
                    'name' => $item->product_name,
                    'sold_count' => $item->sold_count,
                    'revenue' => number_format($item->revenue, 2),
                    'percent' => $totalRevenue > 0 ? round(($item->revenue / $totalRevenue) * 100) . '%' : '0%'
                ];
            });

        $worstProducts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->select(
                'order_items.product_name',
                DB::raw('SUM(order_items.quantity) as sold_count'),
                DB::raw('SUM(order_items.subtotal) as revenue')
            )
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($storeId, fn($q) => $q->where('orders.store_id', $storeId))
            ->whereIn('orders.status', ['completed', 'making'])
            ->groupBy('order_items.product_name')
            ->orderBy('sold_count')
            ->limit(3)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'rank' => $index + 1,
                    'name' => $item->product_name,
                    'sold_count' => $item->sold_count,
                    'revenue' => number_format($item->revenue, 2)
                ];
            });

        $categoryStats = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_name', '=', 'products.name')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'categories.name',
                DB::raw('SUM(order_items.quantity) as sold_count'),
                DB::raw('SUM(order_items.subtotal) as revenue')
            )
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($storeId, fn($q) => $q->where('orders.store_id', $storeId))
            ->whereIn('orders.status', ['completed', 'making'])
            ->groupBy('categories.id', 'categories.name')
            ->get()
            ->map(fn($item) => [
                'name' => $item->name,
                'sold_count' => $item->sold_count,
                'revenue' => number_format($item->revenue, 2)
            ]);

        return response()->json([
            'code' => 200,
            'data' => [
                'period' => "{$startDate} 至 {$endDate}",
                'top_products' => $topProducts,
                'worst_products' => $worstProducts,
                'category_stats' => $categoryStats
            ]
        ]);
    }

    //会员消费统计（管理员）
    
    public function members(): JsonResponse
    {
        $user = JWTAuth::authenticate();
        if (!$user) {
            return response()->json(['code' => 4010, 'message' => '未登录'], 401);
        }

        $levelDistribution = Customer::where('type', 'member')
            ->select(
                'level',
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(total_spent), 0) as total_spent')
            )
            ->groupBy('level')
            ->get()
            ->mapWithKeys(fn($item) => [
                $item->level => [
                    'count' => $item->count,
                    'total_spent' => number_format($item->total_spent, 2)
                ]
            ]);

        $topCustomers = Customer::where('type', 'member')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get()
            ->map(function ($customer, $index) {
                $favoriteProduct = DB::table('order_items')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->select('order_items.product_name', DB::raw('COUNT(*) as cnt'))
                    ->where('orders.customer_id', $customer->id)
                    ->whereIn('orders.status', ['completed', 'making'])
                    ->groupBy('order_items.product_name')
                    ->orderByDesc('cnt')
                    ->first();

                return [
                    'rank' => $index + 1,
                    'name' => $customer->name ?: '-',
                    'phone' => $customer->phone 
                        ? substr($customer->phone, 0, 3) . '****' . substr($customer->phone, -4) 
                        : '-',
                    'level' => $customer->level,
                    'total_spent' => number_format($customer->total_spent, 2),
                    'order_count' => $customer->order_count,
                    'favorite_product' => $favoriteProduct?->product_name ?: '-'
                ];
            });

        $thisMonthStart = now()->firstOfMonth()->format('Y-m-d');
        $lastMonthStart = now()->subMonth()->firstOfMonth()->format('Y-m-d');
        $lastMonthEnd = now()->subMonth()->endOfMonth()->format('Y-m-d');

        $thisMonthNew = Customer::where('type', 'member')
            ->whereBetween('became_member_at', [$thisMonthStart, now()])
            ->count();

        $lastMonthNew = Customer::where('type', 'member')
            ->whereBetween('became_member_at', [$lastMonthStart, $lastMonthEnd])
            ->count();

        $growthRate = $lastMonthNew > 0 
            ? round((($thisMonthNew - $lastMonthNew) / $lastMonthNew) * 100, 1) 
            : ($thisMonthNew > 0 ? 100 : 0);

        return response()->json([
            'code' => 200,
            'data' => [
                'level_distribution' => $levelDistribution,
                'top_customers' => $topCustomers,
                'member_growth' => [
                    'this_month_new' => $thisMonthNew,
                    'last_month_new' => $lastMonthNew,
                    'growth_rate' => "{$growthRate}%"
                ]
            ]
        ]);
    }

    /**
     * 上传饮品图片到OSS
     * POST /api/products/upload-image
     * 权限：manager（店长）
     *
     * 功能：
     * 1. MIME类型白名单校验
     * 2. 图片尺寸白名单校验
     * 3. 文件重命名（随机名+时间戳）
     * 4. 上传至OSS
     * 5. 元数据落库
     */
    public function uploadProductImage(): JsonResponse
    {
        $user = auth('employee')->user();
        if (!$user || $user->role !== 'manager') {
            return response()->json([
                'code' => 4030,
                'message' => '仅管理员可操作'
            ], 403);
        }

        $validator = Validator::make(request()->all(), [
            'image' => 'required|file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 4001,
                'message' => '参数错误',
                'errors' => $validator->errors()
            ], 400);
        }

        /** @var UploadedFile $file */
        $file = request()->file('image');

        // 1. 文件大小校验
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return response()->json([
                'code' => 4002,
                'message' => '文件大小超过限制',
                'errors' => ['image' => '图片大小不能超过5MB']
            ], 400);
        }

        // 2. MIME类型白名单校验
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            return response()->json([
                'code' => 4003,
                'message' => '不支持的文件类型',
                'errors' => [
                    'image' => '仅支持 ' . implode(', ', self::ALLOWED_MIME_TYPES) . ' 格式的图片'
                ]
            ], 400);
        }

        // 3. 扩展名校验
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            $extension = match ($mimeType) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg'
            };
        }

        // 4. 图片尺寸校验
        $imageInfo = getimagesize($file->getRealPath());
        if ($imageInfo === false) {
            return response()->json([
                'code' => 4004,
                'message' => '无法读取图片信息',
                'errors' => ['image' => '无效的图片文件']
            ], 400);
        }

        [$width, $height] = $imageInfo;

        if ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT) {
            return response()->json([
                'code' => 4005,
                'message' => '图片尺寸过小',
                'errors' => [
                    'image' => "图片尺寸不能小于 " . self::MIN_WIDTH . "x" . self::MIN_HEIGHT
                ]
            ], 400);
        }

        if ($width > self::MAX_WIDTH || $height > self::MAX_HEIGHT) {
            return response()->json([
                'code' => 4006,
                'message' => '图片尺寸过大',
                'errors' => [
                    'image' => "图片尺寸不能超过 " . self::MAX_WIDTH . "x" . self::MAX_HEIGHT
                ]
            ], 400);
        }

        try {
            // 5. 生成新的文件名：随机字符串 + 时间戳
            $datePath = date('Y/m/d');
            $randomName = Str::random(16) . '_' . time();
            $newFileName = "{$randomName}.{$extension}";
            $filePath = "products/{$datePath}/{$newFileName}";

            // 6. 上传到OSS（读取文件内容并调用 write 方法）
            $fileContents = file_get_contents($file->getRealPath());
            $uploadSuccess = Storage::disk('oss')->put($filePath, $fileContents);
            
            if (!$uploadSuccess) {
                return response()->json([
                    'code' => 500,
                    'message' => '文件上传到OSS失败，请检查配置',
                    'data' => null
                ], 500);
            }

            // 7. 构建完整的OSS访问URL
            $ossConfig = config('filesystems.disks.oss');
            $cdnDomain = $ossConfig['cdn_domain'] ?? null;
            $bucket = $ossConfig['bucket'] ?? '';
            $endpoint = $ossConfig['endpoint'] ?? '';
            $useSsl = filter_var($ossConfig['ssl'] ?? true, FILTER_VALIDATE_BOOLEAN);

            if ($cdnDomain) {
                $scheme = $useSsl ? 'https' : 'http';
                $fileUrl = rtrim($cdnDomain, '/') . '/' . ltrim($filePath, '/');
                if (!str_starts_with($fileUrl, 'http://') && !str_starts_with($fileUrl, 'https://')) {
                    $fileUrl = $scheme . '://' . $fileUrl;
                }
            } elseif ($bucket && $endpoint) {
                $scheme = $useSsl ? 'https' : 'http';
                $fileUrl = sprintf('%s://%s.%s/%s', $scheme, $bucket, rtrim($endpoint, '/'), ltrim($filePath, '/'));
            } else {
                $fileUrl = '/' . $filePath;
            }

            // 8. 元数据落库
            $fileUpload = FileUpload::create([
                'original_name' => $file->getClientOriginalName(),
                'file_name' => $newFileName,
                'file_path' => $filePath,
                'file_url' => $fileUrl,
                'file_size' => $file->getSize(),
                'mime_type' => $mimeType,
                'extension' => $extension,
                'width' => $width,
                'height' => $height,
                'disk' => 'oss',
                'status' => 'active',
                'ref_count' => 0,
                'uploaded_by' => $user->id,
                'uploaded_at' => now(),
            ]);

            return response()->json([
                'code' => 200,
                'message' => '图片上传成功',
                'data' => [
                    'image_id' => $fileUpload->id,
                    'file_url' => $fileUrl,
                    'file_name' => $newFileName,
                    'original_name' => $file->getClientOriginalName(),
                    'file_size' => $fileUpload->formatted_size,
                    'dimensions' => [
                        'width' => $width,
                        'height' => $height
                    ],
                    'mime_type' => $mimeType,
                    'uploaded_at' => $fileUpload->uploaded_at->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 5001,
                'message' => '图片上传失败',
                'errors' => ['image' => $e->getMessage()]
            ], 500);
        }
    }
}