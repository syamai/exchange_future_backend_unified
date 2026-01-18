<?php

namespace App\Models;

use App\Traits\UsesUnixTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCoinHoldings extends Model
{
    use HasFactory;
    use UsesUnixTimestamps;
    
    protected $table = 'user_coin_holdings';
    protected $guarded = [];

    public $timestamps = false;
}
