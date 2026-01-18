<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportDailyReferralClient extends Model
{
    use HasFactory;
    protected $table = 'report_daily_referral_client';
    protected $guarded = [];

    public $timestamps = false;
}
