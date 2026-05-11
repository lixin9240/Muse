<?php
// database/migrations/2024_01_01_000006_create_product_materials_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_materials', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->unsignedBigInteger('product_sku_id')->comment('规格ID');
            $table->unsignedBigInteger('material_id')->comment('原料ID');
            $table->decimal('quantity', 10, 2)->comment('原料用量');
            $table->timestamps();

            // 一个规格的一个原料只能有一条记录
            $table->unique(['product_sku_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_materials');
    }
};
