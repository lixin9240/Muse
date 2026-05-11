<?php
// database/migrations/2024_01_01_000004_create_product_specs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_specs', function (Blueprint $table) {
            $table->id()->comment('规格ID');
            $table->unsignedBigInteger('product_id')->comment('饮品ID');
            $table->string('name')->comment('规格名称：小杯、中杯、大杯');
            // 1. 中杯行：extra_price = 1.00 (因为中杯加1元)
            // 2. 大杯行：extra_price = 2.00 (因为大杯加2元)
            $table->decimal('extra_price', 8, 2)->default(0)->comment('规格加价金额');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specs');
    }
};
