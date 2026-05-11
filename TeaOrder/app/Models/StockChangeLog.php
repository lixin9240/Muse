<?php
// app/Models/StockChangeLog.php
// 库存变动记录模型

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockChangeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ingredient_id',
        'store_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'order_id',
        'product_id',
        'status',
        'submitter_id',
        'approver_id',
        'approved_at',
        'reason',
        'attachment_url'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'ingredient_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'submitter_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }
}