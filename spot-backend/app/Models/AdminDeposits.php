<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminDeposits extends Model
{
    use HasFactory;

    public $fillable = [
        'user_id',
        'currency',
        'amount',
        'before_balance',
        'after_balance',
        'note'
    ];
}
