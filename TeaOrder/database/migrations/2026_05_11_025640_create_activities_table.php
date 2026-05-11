<?php
// database/migrations/2024_01_01_000009_create_activities_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id()->comment('活动ID');
            $table->unsignedBigInteger('store_id')->comment('门店ID');
            $table->string('name')->comment('活动名称');
            $table->enum('type', ['full_reduction', 'second_half'])->comment('类型：满减、第二杯半价');
            $table->decimal('condition_amount', 10, 2)->nullable()->comment('活动门槛金额，如满减的“满”多少元');
            $table->decimal('benefit_amount', 10, 2)->nullable()->comment('活动优惠金额，如满减的“减”多少元');
            $table->date('start_date')->comment('开始日期');
            $table->date('end_date')->comment('结束日期');
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('状态');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
