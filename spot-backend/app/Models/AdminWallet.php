<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminWallet extends Model
{
    protected $fillable = [
        'type',
        'currency',
        'balance',
        'blockchain_address',
        'blockchain_sub_address'
    ];
}
