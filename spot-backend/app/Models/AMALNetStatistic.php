<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AMALNetStatistic extends Model
{
    public $table = 'amal_net_statistics';
    protected $fillable = [
        'user_id',
        'amal_in',
        'amal_out',
        'statistic_date'

    ];
}
