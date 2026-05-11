<?php
// app/Models/ProductSpec.php
// 产品规格选项模型

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSpec extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'extra_price',
        'sort_order'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function skus(): HasMany
    {
        return $this->hasMany(ProductSku::class);
    }
}