<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Insurance extends Model
{
    public $table = 'insurances';
    public $fillable = [
        'timestamp',
        'currency',
        'wallet_balance'
    ];
}
