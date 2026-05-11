<?php
// database/migrations/2026_05_11_025800_add_foreign_keys_to_stores_and_employees.php
// 统一添加外键约束（在两个表都创建完成后执行）

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // stores.manager_id → employees.id（店长）
        Schema::table('stores', function (Blueprint $table) {
            $table->foreign('manager_id')
                ->references('id')->on('employees')
                ->onDelete('set null');
        });

        // employees.store_id → stores.id（所属门店）
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('store_id')
                ->references('id')->on('stores')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
        });
    }
};
