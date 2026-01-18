<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepositCollect extends Model
{
    //
    public $table = 'deposit_collects';

    public $fillable = [
        'user_id',
        'type',
        'amount',
        'type',
        'created_at',
        'updated_at'
    ];
}
