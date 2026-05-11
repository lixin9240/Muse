<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    #[Fillable(['category_id', 'name', 'base_price', 'image_url', 'description', 'status', 'sort_order'])]

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function specs(): HasMany
    {
        return $this->hasMany(ProductSpec::class);
    }

    public function skus(): HasMany
    {
        return $this->hasMany(ProductSku::class);
    }
}
