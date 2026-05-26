<?php
// database/migrations/2026_05_21_000001_create_file_uploads_table.php
// 文件上传表 - 存储OSS上传的文件元数据

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_uploads', function (Blueprint $table) {
            $table->id()->comment('文件ID');
            $table->string('original_name')->comment('原始文件名');
            $table->string('file_name')->comment('存储文件名');
            $table->string('file_path')->comment('存储路径');
            $table->string('file_url')->comment('访问URL');
            $table->unsignedBigInteger('file_size')->comment('文件大小(字节)');
            $table->string('mime_type')->comment('MIME类型');
            $table->string('extension', 10)->comment('文件扩展名');
            $table->unsignedInteger('width')->nullable()->comment('图片宽度');
            $table->unsignedInteger('height')->nullable()->comment('图片高度');
            $table->string('disk')->default('oss')->comment('存储磁盘');
            $table->enum('status', ['active', 'inactive', 'deleted'])->default('active')->comment('状态');
            $table->unsignedInteger('ref_count')->default(0)->comment('引用计数');
            $table->unsignedBigInteger('uploaded_by')->nullable()->comment('上传者ID');
            $table->timestamp('uploaded_at')->useCurrent()->comment('上传时间');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'mime_type']);
            $table->index('uploaded_by');
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_uploads');
    }
};
