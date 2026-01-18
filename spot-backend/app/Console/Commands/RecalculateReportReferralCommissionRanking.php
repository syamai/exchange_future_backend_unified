<?php

namespace App\Console\Commands;

use App\Models\ReportDailyReferralClient;
use App\Models\ReportReferralCommissionRanking;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecalculateReportReferralCommissionRanking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'referral:ranking-weekly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Report referral commission ranking';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $logTime = Carbon::now()->format('Y-m-d H:i:s');
        $this->info("{$logTime} START-[{$this->signature}] {$this->description} =====================\n");
        
        Log::channel('user_rates_referral_client')->info("START - {$this->description} =====================");

        // Thời gian tính tuần trước
        $now = Carbon::now()->subWeek();
        // $now = Carbon::now();
        $startOfWeek = $now->startOfWeek()->timestamp * 1000;
        $endOfWeek = $now->endOfWeek()->timestamp * 1000;
        // dd(Carbon::createFromTimestampMs($startOfWeek)->toDateTimeString(), Carbon::createFromTimestampMs($endOfWeek)->toDateTimeString());
        
        $week = $now->isoWeek();
        $year = $now->year;
        $reportedAt = $now->endOfWeek()->timestamp * 1000;

        // Lấy dữ liệu daily của tuần
        $dailyData = ReportDailyReferralClient::query()
            ->whereBetween('reported_at', [$startOfWeek, $endOfWeek])
            ->get()
            ->groupBy('user_id');

        $weekly = $dailyData->map(function ($records, $userId) {
            return [
                'user_id' => $userId,
                'uid' => $records->first()->uid,
                'referrals' => $records->max('referral_client_referrer_total'),
                'registration_at' => $records->first()->referral_client_registration_at,
                'total_volume_value' => $records->sum('referral_client_trade_volume_value'),
                'total_commission_value' => $records->sum('referral_client_commission_value'),
                'tier' => $records->last()->referral_client_tier,
                'rate' => $records->last()->referral_client_rate,
                // 'created_at' => now()
            ];
        })->values();

        // Tính rank
        $ranked = $weekly->sortBy([
            ['total_commission_value', 'desc'],
            ['total_volume_value', 'desc'],
            ['referrals', 'desc'],
        ])->values()->map(function ($item, $index) use ($week, $year, $reportedAt) {
            $item['rank'] = $index + 1;
            $item['week'] = $week;
            $item['year'] = $year;
            $item['reported_at'] = $reportedAt;
            return $item;
        });

        foreach ($ranked as $row) {
            foreach ($ranked as $row) {
                try {
                    // ReportReferralCommissionRanking::create($row);
                    $uni = collect($row)->only('user_id', 'week', 'year')->toArray();
                    $data = collect($row)->except('user_id', 'week', 'year')->toArray();
                    // dd($uni, $data);
                    ReportReferralCommissionRanking::updateOrCreate($uni, $data);
                } catch (\Throwable $e) {
                    Log::channel('user_rates_referral_client')->error('Error inserting weekly ranking record', [
                        'record' => $row,
                        'message' => $e->getMessage(),
                        'line' => $e->getLine(),
                        'file' => $e->getFile(),
                    ]);
                }
            }
        }

        Log::channel('user_rates_referral_client')->info('[referral:ranking-weekly] ✅ Ranking calculated', [
            'count' => $ranked->count(),
            'week' => $week,
            'year' => $year,
        ]);

        Log::channel('user_rates_referral_client')->info("END =====================");
        
        $count = $ranked->count();
        $this->info("{$logTime} END-[{$this->signature}] {$this->description} ===Counts:[{$count}]=======job completed===========\n");
    }
}
