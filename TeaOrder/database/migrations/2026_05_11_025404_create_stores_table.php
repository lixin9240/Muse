<?php
// database/migrations/2024_01_01_000010_create_stores_table.php
// 门店表

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id()->comment('门店ID');
            $table->unsignedBigInteger('manager_id')->nullable()->comment('店长ID');
            $table->string('name')->comment('门店名称');
            $table->string('address')->nullable()->comment('门店地址');
            $table->string('phone')->nullable()->comment('联系电话');
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('状态');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
