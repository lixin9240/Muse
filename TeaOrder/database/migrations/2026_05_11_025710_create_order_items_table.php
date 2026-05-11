<?php
// database/migrations/2024_01_01_000011_create_order_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id()->comment('明细ID');
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->unsignedBigInteger('product_spec_id')->comment('规格ID');
            $table->string('product_name')->comment('饮品名称（快照）');
            $table->string('spec_name')->comment('规格名称（快照）');
            $table->integer('quantity')->comment('数量');
            $table->decimal('unit_price', 8, 2)->comment('单价');
            $table->decimal('subtotal', 10, 2)->comment('小计');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
