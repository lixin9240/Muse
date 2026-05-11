<?php
// app/Models/Employee.php
// 员工模型

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Employee extends Authenticatable implements JWTSubject
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'name',
        'phone',
        'password',
        'role',
        'status'
    ];

    protected $hidden = [
        'password'
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function submittedStockLogs(): HasMany
    {
        return $this->hasMany(StockChangeLog::class, 'submitter_id');
    }

    public function approvedStockLogs(): HasMany
    {
        return $this->hasMany(StockChangeLog::class, 'approver_id');
    }
}