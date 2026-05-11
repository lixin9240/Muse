<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    #[Fillable(['phone', 'name', 'email', 'is_verified', 'type', 'level', 'total_spent', 'order_count', 'last_order_at', 'became_member_at', 'first_store_id', 'remark'])]

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
}
