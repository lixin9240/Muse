<?php

namespace App\Http\Controllers;

use App\Models\{Category, Customer, Order, OrderItem, Product, ProductMaterial, ProductSpec};
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class WjcController
{
    //查看饮品列表（点单用）

    public function index(): JsonResponse
    {
        $query = Product::with(['category', 'specs'])
            ->where('status', 'active')
            ->orderBy('sort_order');

        if ($categoryId = request()->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($keyword = request()->input('keyword')) {
            $query->where('name', 'like', "%{$keyword}%");
        }

        $products = $query->get()->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category->name,
                'image_url' => $product->image_url,
                'description' => $product->description,
                'specs' => $product->specs->map(function ($spec) use ($product) {
                    return [
                        'id' => $spec->id,
                        'name' => $spec->name,
                        'price' => number_format($product->base_price + $spec->extra_price, 2)
                    ];
                })
            ];
        });

        return response()->json([
            'code' => 200,
            'data' => $products
        ]);
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
            'image_url' => 'nullable|url',
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
            $product = Product::create([
                'category_id' => request('category_id'),
                'name' => request('name'),
                'base_price' => request('base_price'),
                'image_url' => request('image_url'),
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
}