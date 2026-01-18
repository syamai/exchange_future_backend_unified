<?php

namespace App\Models;

use App\Traits\UsesUnixTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerRealBalanceReport extends Model
{
    use HasFactory;
    use UsesUnixTimestamps;
    protected $guarded = [];
    protected $table = 'player_real_balance_report';

    public $timestamps = false;
}
