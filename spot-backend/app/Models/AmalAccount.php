<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmalAccount extends Model
{
    protected $table = 'amal_accounts';

    public $fillable = [
        'balance',
        'usd_amount',
        'available_balance',
        'blockchain_address',
    ];
}
