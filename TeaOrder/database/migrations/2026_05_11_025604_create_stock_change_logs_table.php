<?php
// database/migrations/2024_01_01_000020_create_stock_change_logs_table.php
// 库存变动记录表

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_change_logs', function (Blueprint $table) {
            $table->id()->comment('记录ID');
            $table->unsignedBigInteger('ingredient_id')->comment('原料ID');
            $table->unsignedBigInteger('store_id')->comment('门店ID');

            // 变动类型
            $table->enum('type', [
                'purchase',              // 采购入库
                'order_deduction',       // 订单扣减
                'product_archive_return', // 下架返还
                'product_archive_scrap',  // 下架报废
                'expired_scrap',         // 过期报废
                'damage_scrap',          // 损坏报废
                'inventory_profit',      // 盘盈
                'inventory_loss',        // 盘亏
                'gift',                  // 赠送
                'adjustment',            // 其他调整
            ])->comment('变动类型');

            $table->integer('quantity')->comment('变动数量（正数增加，负数减少）');
            $table->integer('stock_before')->comment('变动前库存');
            $table->integer('stock_after')->comment('变动后库存');

            // 关联信息
            $table->unsignedBigInteger('order_id')->nullable()->comment('关联订单');
            $table->unsignedBigInteger('product_id')->nullable()->comment('关联产品');

            // 审批信息
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->comment('审批状态');
            $table->unsignedBigInteger('submitter_id')->comment('提交人ID');
            $table->unsignedBigInteger('approver_id')->nullable()->comment('审批人ID');
            $table->timestamp('approved_at')->nullable()->comment('审批时间');

            // 原因说明
            $table->text('reason')->nullable()->comment('变动原因');
            $table->string('attachment_url')->nullable()->comment('附件');

            $table->timestamps();

            $table->index(['ingredient_id', 'created_at']);
            $table->index(['store_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_change_logs');
    }
};
