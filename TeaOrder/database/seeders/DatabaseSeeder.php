<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductMaterial;
use App\Models\ProductSpec;
use App\Models\ProductSku;
use App\Models\Store;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now();

        $this->command->info('🚀 开始生成测试数据...');

        $this->seedStores($now);
        $this->seedEmployees($now);
        $this->seedCategories($now);
        $this->seedProducts($now);
        $this->seedSpecs($now);
        $this->seedSkus($now);
        $this->seedMaterials($now);
        $this->seedProductMaterials($now);
        $this->seedCustomers($now);
        $this->seedActivities($now);

        $this->command->info('✅ 所有测试数据生成完成！');
    }

    private function seedStores($now): void
    {
        $stores = [
            ['name' => '万达广场店', 'address' => '北京市朝阳区建国路93号万达广场B1层', 'phone' => '010-88880001', 'status' => 'active'],
            ['name' => '三里屯店', 'address' => '北京市朝阳区三里屯路19号太古里', 'phone' => '010-88880002', 'status' => 'active'],
            ['name' => '中关村店', 'address' => '北京市海淀区中关村大街27号中关村大厦', 'phone' => '010-88880003', 'status' => 'active'],
        ];

        foreach ($stores as $store) {
            Store::create(array_merge($store, ['created_at' => $now, 'updated_at' => $now]));
        }

        $this->command->info('  ✅ 创建 3 个门店');
    }

    private function seedEmployees($now): void
    {
        $employees = [
            // 1个经理 (director)
            ['store_id' => 1, 'name' => '赵总', 'phone' => '13700137001', 'password' => Hash::make('123456'), 'role' => 'director', 'status' => 'active'],
            // 3个店长 (manager)
            ['store_id' => 1, 'name' => '张伟', 'phone' => '13800138001', 'password' => Hash::make('123456'), 'role' => 'manager', 'status' => 'active'],
            ['store_id' => 2, 'name' => '李娜', 'phone' => '13800138002', 'password' => Hash::make('123456'), 'role' => 'manager', 'status' => 'active'],
            ['store_id' => 3, 'name' => '王强', 'phone' => '13800138003', 'password' => Hash::make('123456'), 'role' => 'manager', 'status' => 'active'],
            // 3个员工 (staff)
            ['store_id' => 1, 'name' => '刘芳', 'phone' => '13900139001', 'password' => Hash::make('123456'), 'role' => 'staff', 'status' => 'active'],
            ['store_id' => 2, 'name' => '陈明', 'phone' => '13900139002', 'password' => Hash::make('123456'), 'role' => 'staff', 'status' => 'active'],
            ['store_id' => 3, 'name' => '杨洋', 'phone' => '13900139003', 'password' => Hash::make('123456'), 'role' => 'staff', 'status' => 'active'],
        ];

        foreach ($employees as $employee) {
            Employee::create(array_merge($employee, ['created_at' => $now, 'updated_at' => $now]));
        }

        $this->command->info('  ✅ 创建 1 个经理、3 个店长和 3 个员工');
    }

    private function seedCategories($now): void
    {
        $categories = [
            ['name' => '经典奶茶', 'sort_order' => 1],
            ['name' => '鲜果茶饮', 'sort_order' => 2],
            ['name' => '纯茶系列', 'sort_order' => 3],
            ['name' => '咖啡饮品', 'sort_order' => 4],
        ];

        foreach ($categories as $category) {
            Category::create(array_merge($category, ['status' => 'active', 'created_at' => $now, 'updated_at' => $now]));
        }

        $this->command->info('  ✅ 创建 4 个分类');
    }

    private function seedProducts($now): void
    {
        $products = [
            ['category_id' => 1, 'name' => '招牌珍珠奶茶', 'base_price' => 18.00, 'description' => '香浓奶茶搭配Q弹珍珠，经典不衰'],
            ['category_id' => 2, 'name' => '满杯水果茶', 'base_price' => 25.00, 'description' => '多种新鲜水果，维C满满'],
            ['category_id' => 3, 'name' => '龙井绿茶', 'base_price' => 16.00, 'description' => '西湖龙井，清香淡雅'],
            ['category_id' => 4, 'name' => '拿铁咖啡', 'base_price' => 24.00, 'description' => '丝滑拿铁，奶香浓郁'],
        ];

        foreach ($products as $product) {
            Product::create(array_merge($product, ['image_url' => '', 'status' => 'active', 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]));
        }

        $this->command->info('  ✅ 创建 10 个产品');
    }

    private function seedSpecs($now): void
    {
        $specs = [
            // 杯型规格
            ['product_id' => 1, 'name' => '中杯', 'extra_price' => 0, 'sort_order' => 1],
            ['product_id' => 1, 'name' => '大杯', 'extra_price' => 3, 'sort_order' => 2],
            ['product_id' => 2, 'name' => '中杯', 'extra_price' => 0, 'sort_order' => 1],
            ['product_id' => 2, 'name' => '大杯', 'extra_price' => 4, 'sort_order' => 2],
            ['product_id' => 3, 'name' => '中杯', 'extra_price' => 0, 'sort_order' => 1],
            ['product_id' => 3, 'name' => '大杯', 'extra_price' => 3, 'sort_order' => 2],
            ['product_id' => 4, 'name' => '中杯', 'extra_price' => 0, 'sort_order' => 1],
            ['product_id' => 4, 'name' => '大杯', 'extra_price' => 5, 'sort_order' => 2],

            // 温度规格（仅对部分产品）
            ['product_id' => 1, 'name' => '去冰', 'extra_price' => 0, 'sort_order' => 10],
            ['product_id' => 1, 'name' => '常温', 'extra_price' => 0, 'sort_order' => 11],
            ['product_id' => 1, 'name' => '热饮', 'extra_price' => 0, 'sort_order' => 12],

            // 甜度规格（仅对奶茶类）
            ['product_id' => 1, 'name' => '全糖', 'extra_price' => 0, 'sort_order' => 20],
            ['product_id' => 1, 'name' => '七分糖', 'extra_price' => 0, 'sort_order' => 21],
        ];

        foreach ($specs as $spec) {
            ProductSpec::create(array_merge($spec, ['created_at' => $now, 'updated_at' => $now]));
        }

        $this->command->info('  ✅ 创建 ' . count($specs) . ' 个规格选项');
    }

    private function seedSkus($now): void
    {
        $skus = [
            // 招牌珍珠奶茶 - 中杯
            ['product_id' => 1, 'spec_ids' => '1,21,25', 'price' => 18.00, 'sku_code' => 'ZM001-M-QW'],

            // 招牌珍珠奶茶 - 大杯
            ['product_id' => 1, 'spec_ids' => '2,21,25', 'price' => 21.00, 'sku_code' => 'ZM001-L-QW'],

            // 满杯水果茶 - 中杯
            ['product_id' => 4, 'spec_ids' => '7', 'price' => 25.00, 'sku_code' => 'SG004-M'],
            // 满杯水果茶 - 大杯
            ['product_id' => 4, 'spec_ids' => '8', 'price' => 30.00, 'sku_code' => 'SG004-L'],

            // 龙井绿茶 - 中杯
            ['product_id' => 8, 'spec_ids' => '15,52', 'price' => 16.00, 'sku_code' => 'LJLQC008-M'],
            // 龙井绿茶 - 大杯
            ['product_id' => 8, 'spec_ids' => '16,52', 'price' => 19.00, 'sku_code' => 'LJLQC008-L'],

            // 拿铁咖啡 - 中杯
            ['product_id' => 10, 'spec_ids' => '19,55', 'price' => 24.00, 'sku_code' => 'NTKF010-M-HOT'],
            // 拿铁咖啡 - 大杯
            ['product_id' => 10, 'spec_ids' => '20,55', 'price' => 28.00, 'sku_code' => 'NTKF010-L-HOT'],
        ];

        foreach ($skus as $sku) {
            ProductSku::create(array_merge($sku, ['status' => 'active', 'created_at' => $now, 'updated_at' => $now]));
        }

        $this->command->info('  ✅ 创建 ' . count($skus) . ' 个SKU');
    }

    private function seedMaterials($now): void
    {
        $materialsByStore = [
            1 => [// 万达广场店
                ['name' => '纯牛奶', 'unit' => 'ml', 'stock' => 50000, 'warning_stock' => 5000],
                ['name' => '绿茶包', 'unit' => '个', 'stock' => 800, 'warning_stock' => 80],
                ['name' => '珍珠粉圆', 'unit' => 'g', 'stock' => 10000, 'warning_stock' => 1000],
                ['name' => '果糖浆', 'unit' => 'ml', 'stock' => 18000, 'warning_stock' => 1800],
                ['name' => '西柚', 'unit' => '个', 'stock' => 150, 'warning_stock' => 15],
                ['name' => '西瓜', 'unit' => '个', 'stock' => 150, 'warning_stock' => 15],
                ['name' => '浓缩咖啡液', 'unit' => 'ml', 'stock' => 10000, 'warning_stock' => 1000],
            ],
            2 => [// 三里屯店
                ['name' => '纯牛奶', 'unit' => 'ml', 'stock' => 45000, 'warning_stock' => 4500],
                ['name' => '红茶包', 'unit' => '个', 'stock' => 900, 'warning_stock' => 90],
                ['name' => '绿茶包', 'unit' => '个', 'stock' => 700, 'warning_stock' => 70],
                ['name' => '珍珠粉圆', 'unit' => 'g', 'stock' => 9000, 'warning_stock' => 900],
                ['name' => '果糖浆', 'unit' => 'ml', 'stock' => 18000, 'warning_stock' => 1800],
                ['name' => '西柚', 'unit' => '个', 'stock' => 150, 'warning_stock' => 15],
                ['name' => '西瓜', 'unit' => '个', 'stock' => 150, 'warning_stock' => 15],
                ['name' => '浓缩咖啡液', 'unit' => 'ml', 'stock' => 8000, 'warning_stock' => 800],
            ],
            3 => [// 中关村店
             ['name' => '纯牛奶', 'unit' => 'ml', 'stock' => 45000, 'warning_stock' => 4500],
                ['name' => '红茶包', 'unit' => '个', 'stock' => 900, 'warning_stock' => 90],
                ['name' => '绿茶包', 'unit' => '个', 'stock' => 700, 'warning_stock' => 70],
                ['name' => '珍珠粉圆', 'unit' => 'g', 'stock' => 9000, 'warning_stock' => 900],
                ['name' => '果糖浆', 'unit' => 'ml', 'stock' => 18000, 'warning_stock' => 1800],
                ['name' => '西柚', 'unit' => '个', 'stock' => 150, 'warning_stock' => 15],
                ['name' => '西瓜', 'unit' => '个', 'stock' => 150, 'warning_stock' => 15],
                ['name' => '浓缩咖啡液', 'unit' => 'ml', 'stock' => 8000, 'warning_stock' => 800],
            ],
        ];

        foreach ($materialsByStore as $storeId => $materials) {
            foreach ($materials as $material) {
                Material::create(array_merge($material, [
                    'store_id' => $storeId,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        $totalMaterials = array_sum(array_map(fn($m) => count($m), $materialsByStore));
        $this->command->info("  ✅ 创建 {$totalMaterials} 条原料库存记录（每店10种）");
    }

    private function seedProductMaterials(): void
    {
        $relations = [
            // 招牌珍珠奶茶
            ['sku_code' => 'ZM001-M-QW', 'material_name' => '纯牛奶', 'quantity' => 200],
            ['sku_code' => 'ZM001-M-QW', 'material_name' => '珍珠粉圆', 'quantity' => 50],
            ['sku_code' => 'ZM001-M-QW', 'material_name' => '果糖浆', 'quantity' => 30],
            ['sku_code' => 'ZM001-L-QW', 'material_name' => '纯牛奶', 'quantity' => 250],
            ['sku_code' => 'ZM001-L-QW', 'material_name' => '珍珠粉圆', 'quantity' => 60],
            ['sku_code' => 'ZM001-L-QW', 'material_name' => '果糖浆', 'quantity' => 30],

            // 满杯水果茶
            ['sku_code' => 'SG004-M', 'material_name' => '绿茶包', 'quantity' => 2],
            ['sku_code' => 'SG004-M', 'material_name' => '果糖浆', 'quantity' => 20],
            ['sku_code' => 'SG004-L', 'material_name' => '绿茶包', 'quantity' => 3],
            ['sku_code' => 'SG004-L', 'material_name' => '果糖浆', 'quantity' => 25],

            // 龙井绿茶
            ['sku_code' => 'LJLQC008-M', 'material_name' => '绿茶包', 'quantity' => 2],
            ['sku_code' => 'LJLQC008-L', 'material_name' => '绿茶包', 'quantity' => 3],

            // 拿铁咖啡
            ['sku_code' => 'NTKF010-M-HOT', 'material_name' => '浓缩咖啡液', 'quantity' => 40],
            ['sku_code' => 'NTKF010-M-HOT', 'material_name' => '纯牛奶', 'quantity' => 200],
            ['sku_code' => 'NTKF010-M-ICE', 'material_name' => '浓缩咖啡液', 'quantity' => 40],
            ['sku_code' => 'NTKF010-M-ICE', 'material_name' => '纯牛奶', 'quantity' => 200],
            ['sku_code' => 'NTKF010-L-HOT', 'material_name' => '浓缩咖啡液', 'quantity' => 50],
            ['sku_code' => 'NTKF010-L-HOT', 'material_name' => '纯牛奶', 'quantity' => 280],
        ];

        foreach ($relations as $relation) {
            $sku = ProductSku::where('sku_code', $relation['sku_code'])->first();
            $material = Material::where('name', $relation['material_name'])->first();

            if ($sku && $material) {
                ProductMaterial::create([
                    'product_sku_id' => $sku->id,
                    'material_id' => $material->id,
                    'quantity' => $relation['quantity'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('  ✅ 创建产品-原料关系');
    }

    private function seedCustomers($now): void
    {
        $customers = [
            ['phone' => '15000000007', 'name' => '林子豪', 'level' => 'silver', 'total_spent' => 198.00, 'order_count' => 5, 'type' => 'member'],
            ['phone' => '15000000008', 'name' => '何晓燕', 'level' => 'none', 'total_spent' => 45.00, 'order_count' => 1, 'type' => 'guest'],
        ];

        foreach ($customers as $customer) {
            Customer::create(array_merge($customer, [
                'email' => null,
                'is_verified' => true,
                'first_store_id' => 1,
                'remark' => '',
                'became_member_at' => $customer['type'] === 'member' ? $now : null,
                'last_order_at' => $customer['order_count'] > 0 ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->command->info('  ✅ 创建 8 个顾客（含不同会员等级）');
    }

    private function seedActivities($now): void
    {
        $activities = [
            [
                'store_id' => 1,
                'name' => '新店开业满减活动',
                'type' => 'full_reduction',
                'condition_amount' => 50.00,
                'benefit_amount' => 10.00,
                'start_date' => $now->copy()->subDays(7),
                'end_date' => $now->copy()->addDays(23),
                'status' => 'active',
            ],
            [
                'store_id' => 2,
                'name' => '夏日特惠第二杯半价',
                'type' => 'second_half',
                'condition_amount' => 0,
                'benefit_amount' => 0,
                'start_date' => $now->copy()->subDays(3),
                'end_date' => $now->copy()->addDays(58),
                'status' => 'active',
            ],
        ];

        foreach ($activities as $activity) {
            Activity::create(array_merge($activity, ['created_at' => $now, 'updated_at' => $now]));
        }

        $this->command->info('  ✅ 创建 2 个活动（满减+第二杯半价）');
    }
}
