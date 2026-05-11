<?php
// database/migrations/2024_01_01_000005_create_materials_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id()->comment('原料ID');
            $table->unsignedBigInteger('store_id')->comment('门店ID');
            $table->string('name')->comment('原料名称');
            $table->string('unit')->comment('单位：ml、g、个');
            $table->decimal('stock', 10, 2)->default(0)->comment('当前库存');
            $table->decimal('warning_stock', 10, 2)->default(0)->comment('警戒库存');
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('状态');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
