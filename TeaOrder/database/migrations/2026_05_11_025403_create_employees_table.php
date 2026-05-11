<?php
// database/migrations/2026_05_11_025403_create_employees_table.php
// 员工表（时间戳更早，确保在 stores 之前创建）

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id()->comment('员工ID');
            $table->unsignedBigInteger('store_id')->comment('门店ID');
            $table->string('name')->comment('姓名');
            $table->string('phone')->comment('手机号');
            $table->string('password')->comment('密码');
            $table->enum('role', ['manager', 'staff'])->default('staff')->comment('角色：店长、店员');
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('状态');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
