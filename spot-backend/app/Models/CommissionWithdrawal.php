<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionWithdrawal extends Model
{
    protected $fillable = [
        'user_id',
        'amount'
    ];

    protected $casts = [
        'amount' => 'decimal:8'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 