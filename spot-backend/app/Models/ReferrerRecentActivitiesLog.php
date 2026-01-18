<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferrerRecentActivitiesLog extends Model
{
    use HasFactory;
    protected $table = 'referrer_recent_activities_log';
    protected $guarded = [];
}
