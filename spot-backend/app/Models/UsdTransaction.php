<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsdTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'amount',
        'bank_name',
        'bank_branch',
        'account_name',
        'account_no',
        'code',
        'fee',
        'created_at',
        'updated_at',
        'status'
    ];

    public function scopeFilterWithdraw($query)
    {
        return $query->where('amount', '<', 0);
    }

    public function scopeFilterDeposit($query)
    {
        return $query->where('amount', '>', 0);
    }
}
