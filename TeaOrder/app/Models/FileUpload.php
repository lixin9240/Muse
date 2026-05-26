<?php
// app/Models/FileUpload.php
// 文件上传模型 - 存储OSS上传的文件元数据

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FileUpload extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'original_name',
        'file_name',
        'file_path',
        'file_url',
        'file_size',
        'mime_type',
        'extension',
        'width',
        'height',
        'disk',
        'status',
        'ref_count',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'ref_count' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    /**
     * 获取文件类型标签
     */
    public function getFileTypeLabelAttribute(): string
    {
        return match ($this->mime_type) {
            'image/jpeg', 'image/jpg' => 'JPEG图片',
            'image/png' => 'PNG图片',
            'image/webp' => 'WebP图片',
            'image/gif' => 'GIF图片',
            default => '未知类型',
        };
    }

    /**
     * 获取格式化文件大小
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    /**
     * 关联上传者
     */
    public function uploader()
    {
        return $this->belongsTo(Employee::class, 'uploaded_by');
    }
}
