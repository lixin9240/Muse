<?php
// database/migrations/2024_01_01_000012_create_products_table.php
// 饮品表

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id()->comment('饮品ID');
            $table->unsignedBigInteger('category_id')->comment('分类ID');
            $table->string('name')->comment('饮品名称');
            $table->decimal('base_price', 8, 2)->comment('小杯基础售价');
            $table->string('image_url')->nullable()->comment('饮品图片链接');
            $table->text('description')->nullable()->comment('饮品描述');
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('状态：active-上架，inactive-下架');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
