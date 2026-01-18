<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ReportReferralCommissionRanking extends Model
{
    use HasFactory;

    protected $table = 'report_referral_commission_ranking';
    protected $guarded = [];

    // public $timestamps = false;


    public static function lastWeek() {
        $record = self::query()
        ->select(DB::raw("year, week, reported_at as last_updated"))
        ->orderByDesc('year')
        ->orderByDesc('week')
        ->limit(1)
        ->first();

        if($record) return $record;

        return null; 
        
    }
    public static function rank($referrerId) {
        $latest = self::lastWeek();
        if($latest) {
            $result = self::query()
            ->where('user_id', $referrerId)
            ->where('week', $latest->week)
            ->where('year', $latest->year)
            ->first();

            return $result->rank ?? null;
        }
        return null;
    }

    public static function maxRank() {
        $latest = self::lastWeek();

        if($latest) {
         $rank = self::query()
            ->where('week', $latest->week)
            ->where('year', $latest->year)
            ->max('rank');

            if($rank) return $rank;
        }
        
        return null;
    }
}
