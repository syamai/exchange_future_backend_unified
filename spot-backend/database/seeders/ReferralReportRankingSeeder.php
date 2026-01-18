<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ReportDailyReferralClient;
use App\Models\ReportReferralCommissionRanking;
use Illuminate\Support\Carbon;

class ReferralReportRankingSeeder extends Seeder
{
    public function run(): void
    {
        // 🔹 Generate daily report data
        ReportDailyReferralClient::factory()
            ->count(50) // 50 users x 1 ngày, hoặc chỉnh để mỗi user có nhiều ngày
            ->create();

        // 🔹 Calculate weekly report from that (giả lập rank)
        $week = Carbon::now()->subWeek()->isoWeek();
        $year = Carbon::now()->subWeek()->year;
        $reportedAt = Carbon::now()->subWeek()->endOfWeek()->timestamp * 1000;

        $summary = ReportDailyReferralClient::where('reported_at', '>=', Carbon::now()->subWeek()->startOfWeek()->timestamp * 1000)
            ->where('reported_at', '<=', Carbon::now()->subWeek()->endOfWeek()->endOfDay()->timestamp * 1000)
            ->get()
            ->groupBy('user_id')
            ->map(function ($records, $userId) use ($week, $year, $reportedAt) {
                return [
                    'user_id' => $userId,
                    'uid' => $records->first()->uid,
                    'referrals' => $records->max('referral_client_referrer_total'),
                    'registration_at' => $records->first()->referral_client_registration_at,
                    'total_volume_value' => $records->sum('referral_client_trade_volume_value'),
                    'total_commission_value' => $records->sum('referral_client_commission_value'),
                    'tier' => $records->last()->referral_client_tier,
                    'reported_at' => $reportedAt,
                    'created_at' => now(),
                    'week' => $week,
                    'year' => $year,
                ];
            })->sortByDesc('total_commission_value')
              ->values()
              ->map(function ($item, $index) {
                  $item['rank'] = $index + 1;
                  return $item;
              });

        foreach ($summary as $row) {
            ReportReferralCommissionRanking::create($row);
        }
    }
}
