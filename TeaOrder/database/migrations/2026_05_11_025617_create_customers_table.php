<?php
// database/migrations/2024_01_01_000016_create_customers_table.php
// 顾客表（包含普通顾客和会员）

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id()->comment('顾客ID');

            // 基本信息（普通顾客和会员都有）
            $table->string('phone')->nullable()->comment('手机号');
            $table->string('name')->nullable()->comment('姓名');
            //邮箱字段：允许为空，因为普通顾客(guest)可能没有邮箱
            $table->string('email')->nullable()->unique()->comment('会员邮箱（用于激活和登录）');

            //激活状态：0-未激活(普通顾客/待激活会员), 1-已激活(正式会员)
            // 默认设为 0，确保只有验证过邮箱的才是正式会员
            $table->boolean('is_verified')->default(false)->comment('是否已验证邮箱(激活会员)');

            // 类型区分
            $table->enum('type', ['guest', 'member'])->default('guest')->comment('类型：guest-普通顾客，member-会员');

            // 会员专属字段（type=member时有效）
            $table->enum('level', ['none', 'silver', 'gold', 'diamond'])->default('none')->comment('等级');
            $table->decimal('total_spent', 10, 2)->default(0)->comment('累计消费金额');
            $table->integer('order_count')->default(0)->comment('订单次数');
            $table->timestamp('last_order_at')->nullable()->comment('最后消费时间');
            $table->timestamp('became_member_at')->nullable()->comment('成为会员时间');

            // 首次消费门店（用于统计）
            $table->unsignedBigInteger('first_store_id')->nullable()->comment('首次消费门店');

            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
            $table->index('type');
            $table->index(['type', 'level', 'total_spent']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
