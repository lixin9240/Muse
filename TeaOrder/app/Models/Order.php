<?php
// app/Models/Order.php
// 订单模型

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_no',
        'store_id',
        'customer_id',
        'original_amount',
        'discount_amount',
        'final_amount',
        'activity_id',
        'discount_desc',
        'customer_level_at_order',
        'member_discount_rate',
        'status',
        'remark',
        'completed_at'
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'member_discount_rate' => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockChangeLogs(): HasMany
    {
        return $this->hasMany(StockChangeLog::class);
    }
}