<?php
// database/migrations/2026_05_11_025900_add_all_foreign_keys.php
// 统一添加所有外键约束（在所有表创建完成后执行）

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ========== 门店 & 员工（循环依赖） ==========
        Schema::table('stores', function (Blueprint $table) {
            $table->foreign('manager_id')
                ->references('id')->on('employees')
                ->onDelete('set null');
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('store_id')
                ->references('id')->on('stores')
                ->onDelete('cascade');
        });

        // ========== 产品相关 ==========
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')->on('categories')
                ->onDelete('cascade');
        });
        Schema::table('product_specs', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')->on('products')
                ->onDelete('cascade');
        });

        // product_skus 的 product_id（创建时已用 foreignId，但没加 constrained）
        Schema::table('product_skus', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')->on('products')
                ->onDelete('cascade');
        });

        // ========== 原料 & 产品原料关联 ==========
        Schema::table('materials', function (Blueprint $table) {
            $table->foreign('store_id')
                ->references('id')->on('stores')
                ->onDelete('cascade');
        });
        Schema::table('product_materials', function (Blueprint $table) {
            $table->foreign('product_sku_id')
                ->references('id')->on('product_skus')
                ->onDelete('cascade');
            $table->foreign('material_id')
                ->references('id')->on('materials')
                ->onDelete('cascade');
        });

        // ========== 库存变动记录 ==========
        Schema::table('stock_change_logs', function (Blueprint $table) {
            $table->foreign('ingredient_id')
                ->references('id')->on('materials')
                ->onDelete('restrict');
            $table->foreign('store_id')
                ->references('id')->on('stores')
                ->onDelete('cascade');
            $table->foreign('order_id')
                ->references('id')->on('orders')
                ->onDelete('set null');
            $table->foreign('product_id')
                ->references('id')->on('products')
                ->onDelete('set null');
            $table->foreign('submitter_id')
                ->references('id')->on('employees')
                ->onDelete('restrict');
            $table->foreign('approver_id')
                ->references('id')->on('employees')
                ->onDelete('set null');
        });

        // ========== 顾客 ==========
        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('first_store_id')
                ->references('id')->on('stores')
                ->onDelete('set null');
        });

        // ========== 活动 ==========
        Schema::table('activities', function (Blueprint $table) {
            $table->foreign('store_id')
                ->references('id')->on('stores')
                ->onDelete('cascade');
        });

        // ========== 订单 ==========
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('store_id')
                ->references('id')->on('stores')
                ->onDelete('cascade');
            $table->foreign('customer_id')
                ->references('id')->on('customers')
                ->onDelete('cascade');
            $table->foreign('activity_id')
                ->references('id')->on('activities')
                ->onDelete('set null');
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });

        // ========== 订单明细 ==========
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('order_id')
                ->references('id')->on('orders')
                ->onDelete('cascade');
            $table->foreign('product_spec_id')
                ->references('id')->on('product_specs')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        $tables = [
            'order_items' => ['order_id', 'product_spec_id'],
            'orders' => ['store_id', 'customer_id', 'activity_id', 'user_id'],
            'activities' => ['store_id'],
            'customers' => ['first_store_id'],
            'stock_change_logs' => ['ingredient_id', 'store_id', 'order_id', 'product_id', 'submitter_id', 'approver_id'],
            'product_materials' => ['product_sku_id', 'material_id'],
            'materials' => ['store_id'],
            'product_skus' => ['product_id'],
            'product_specs' => ['product_id'],
            'products' => ['category_id'],
            'employees' => ['store_id'],
            'stores' => ['manager_id'],
        ];

        foreach ($tables as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $column) {
                    $t->dropForeign([$column]);
                }
            });
        }
    }
};
