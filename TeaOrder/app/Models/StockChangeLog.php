<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockChangeLog extends Model
{
    use HasFactory;

    #[Fillable(['ingredient_id', 'store_id', 'type', 'quantity', 'stock_before', 'stock_after', 'order_id', 'product_id', 'status', 'submitter_id', 'approver_id', 'approved_at', 'reason', 'attachment_url'])]

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'ingredient_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'submitter_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }
}
