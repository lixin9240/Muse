<?php
// app/Models/Customer.php
// 顾客模型

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'phone',
        'name',
        'email',
        'is_verified',
        'type',
        'level',
        'total_spent',
        'order_count',
        'last_order_at',
        'became_member_at',
        'first_store_id',
        'remark'
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'total_spent' => 'decimal:2',
        'last_order_at' => 'datetime',
        'became_member_at' => 'datetime',
    ];

    /**
     * @used-by FmyController::createOrder
     * @used-by LXController::show
     */
    public function firstStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'first_store_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @used-by FmyController::createOrder
     * @used-by LXController::show
     */
    public function getDiscountRateAttribute(): float
    {
        return match ($this->level) {
            'diamond' => 0.85,
            'gold' => 0.90,
            'silver' => 0.95,
            default => 1.00,
        };
    }

    /**
     * @used-by FmyController::createOrder
     * @used-by LXController::show
     */
    public function getLevelNameAttribute(): string
    {
        return match ($this->level) {
            'diamond' => '钻石卡',
            'gold' => '金卡',
            'silver' => '银卡',
            default => '普通顾客',
        };
    }
}
