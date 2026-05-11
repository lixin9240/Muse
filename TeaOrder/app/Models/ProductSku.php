<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSku extends Model
{
    use HasFactory;

    #[Fillable(['product_id', 'spec_ids', 'price', 'sku_code', 'status'])]

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProductMaterial::class);
    }
}
