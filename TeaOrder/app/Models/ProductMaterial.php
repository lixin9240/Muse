<?php
// app/Models/ProductMaterial.php
// 产品原料关联模型

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_sku_id',
        'material_id',
        'quantity'
    ];

    public function productSku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}