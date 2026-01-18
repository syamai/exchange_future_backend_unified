<?php

namespace App\Models;

use App\Traits\UsesUnixTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCoinPendingsLogs extends Model
{
    use HasFactory;
    use UsesUnixTimestamps;

    protected $table = 'user_coin_pendings_logs';
    protected $guarded = [];

    public $timestamps = false;
}
