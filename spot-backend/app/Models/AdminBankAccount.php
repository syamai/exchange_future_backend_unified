<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminBankAccount extends Model
{
    protected $fillable = [
        'bank_name',
        'bank_branch',
        'account_no',
        'account_name',
        'note',
        'balance'
    ];

    public function getBalanceAttribute($value)
    {
        return preg_replace("/\.?0*$/", '', '' . $value);
    }
}
