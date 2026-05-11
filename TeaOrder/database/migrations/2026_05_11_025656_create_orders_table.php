<?php
// database/migrations/2024_01_01_000018_create_orders_table.php
// 订单表

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id()->comment('订单ID');
            $table->string('order_no')->unique()->comment('订单编号');
            $table->unsignedBigInteger('store_id')->comment('门店ID');

            // 顾客关联
            $table->unsignedBigInteger('customer_id')->comment('顾客ID');

            // 金额信息
            $table->decimal('original_amount', 10, 2)->comment('原价');
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('优惠金额');
            $table->decimal('final_amount', 10, 2)->comment('实付金额');

            // 优惠信息
            $table->unsignedBigInteger('activity_id')->nullable()->comment('关联的优惠活动ID');
            $table->string('discount_desc')->nullable()->comment('优惠说明');

            // 会员折扣快照（下单时的等级和折扣率）
            $table->enum('customer_level_at_order', ['none', 'silver', 'gold', 'diamond'])->default('none')->comment('下单时等级');
            $table->decimal('member_discount_rate', 3, 2)->nullable()->comment('会员折扣率');

            //更新状态枚举值：pending(审核中), making(制作中), completed(已完成), cancelled(已取消)
            $table->enum('status', ['pending', 'making', 'completed', 'cancelled'])->default('pending')->comment('订单状态');
            $table->text('remark')->nullable()->comment('备注');
            $table->unsignedBigInteger('user_id')->comment('用户id');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'created_at']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
