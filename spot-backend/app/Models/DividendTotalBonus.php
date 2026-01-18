<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DividendTotalBonus extends Model
{
    public $table = 'dividend_total_bonus';

    public $fillable = [
        'coin',
        'total_bonus'
    ];
}
