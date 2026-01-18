<?php

namespace App\Models;

use App\Traits\UsesUnixTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCoinPendings extends Model
{
    use HasFactory;
    use UsesUnixTimestamps;
    
    protected $table = 'user_coin_pendings';
    protected $guarded = [];

    public $timestamps = false;
}
