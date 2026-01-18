<?php

namespace App\Models;

use App\Traits\UsesUnixTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GainsStatisticsOverview extends Model
{
    
    use HasFactory;
    use UsesUnixTimestamps;
    protected $primaryKey = 'user_id';
    protected $table = 'gains_statistics_overview';
    protected $guarded = [];

    public $timestamps = false;
}
