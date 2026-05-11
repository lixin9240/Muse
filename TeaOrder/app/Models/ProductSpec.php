<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSpec extends Model
{
    use HasFactory;

    #[Fillable(['product_id', 'name', 'extra_price', 'sort_order'])]

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
