<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    #[Fillable(['store_id', 'name', 'unit', 'stock', 'warning_stock', 'status'])]

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function productMaterials(): HasMany
    {
        return $this->hasMany(ProductMaterial::class);
    }

    public function stockChangeLogs(): HasMany
    {
        return $this->hasMany(StockChangeLog::class, 'ingredient_id');
    }
}
