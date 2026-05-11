<?php
// database/migrations/2026_05_11_025515_create_product_skus_table.php
// 产品SKU表（规格+价格组合，如"大杯+少糖=15元"）

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_skus', function (Blueprint $table) {
            $table->id()->comment('SKU ID');
            $table->foreignId('product_id')->comment('产品ID');
            $table->string('spec_ids')->comment('规格组合ID，逗号分隔，如 "1,3" 表示中杯+少糖');
            $table->decimal('price', 8, 2)->comment('SKU最终售价');
            $table->string('sku_code')->unique()->comment('SKU编码');
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('状态');
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_skus');
    }
};
