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

    public function firstStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'first_store_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getDiscountRateAttribute(): float
    {
        return match ($this->level) {
            'diamond' => 0.85,
            'gold' => 0.90,
            'silver' => 0.95,
            default => 1.00,
        };
    }

    public function getLevelNameAttribute(): string
    {
        return match ($this->level) {
            'diamond' => '钻石卡',
            'gold' => '金卡',
            'silver' => '银卡',
            default => '普通顾客',
        };
    }

    /**
     * 根据消费金额自动升级会员等级
     * 银卡(2000) -> 金卡(4000) -> 钻石卡
     */
    public function upgradeLevelBySpent(): void
    {
        // 只有已验证的会员才能升级
        if (!$this->is_verified || $this->type !== 'member') {
            return;
        }

        $newLevel = $this->level;

        // 钻石卡已经是最高等级
        if ($this->level === 'diamond') {
            return;
        }

        // 满4000升级为钻石卡
        if ($this->total_spent >= 4000) {
            $newLevel = 'diamond';
        }
        // 满2000升级为金卡
        elseif ($this->total_spent >= 2000) {
            $newLevel = 'gold';
        }

        // 等级有变化才更新
        if ($newLevel !== $this->level) {
            $this->update(['level' => $newLevel]);
        }
    }
}