<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferrerClientLevelLog extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'referrer_client_levels_logs';
}
