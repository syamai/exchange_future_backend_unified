<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Funding extends Model
{
    public $table = 'fundings';

    public $fillable = [
        'timestamp',
        'symbol',
        'funding_interval',
        'funding_rate',
        'funding_rate_daily',
        'created_at',
        'updated_at',
    ];
}
