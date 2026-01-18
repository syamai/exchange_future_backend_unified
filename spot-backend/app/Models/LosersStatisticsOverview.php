<?php

namespace App\Models;

use App\Traits\UsesUnixTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LosersStatisticsOverview extends Model
{
    use HasFactory;
    use UsesUnixTimestamps;
    
    protected $table = 'losers_statistics_overview';
    protected $guarded = [];

    public $timestamps = false;
}
