<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    #[Fillable(['manager_id', 'name', 'address', 'phone', 'status'])]

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
