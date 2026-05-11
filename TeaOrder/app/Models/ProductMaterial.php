<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMaterial extends Model
{
    use HasFactory;

    #[Fillable(['product_sku_id', 'material_id', 'quantity'])]

    public function productSku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
