<?php

namespace App\Console\Commands;

use App\Models\LosersStatisticsOverview;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculateTopLosersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overview:top-loses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate Top Losers based on peak vs current asset value';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $logTime = Carbon::now()->format('Y-m-d H:i:s');
        $this->info("{$logTime} START-[{$this->signature}] {$this->description} =====================\n");
        
        try {
            // Step 1: snapshot gần nhất của mỗi user
            $currentAssets = DB::table('user_asset_snapshots as s')
                ->select(
                    's.user_id',
                    's.total_asset_value as currentAssetValue',
                    's.currency',
                    's.total_buy',
                    's.total_sell',
                    's.total_buy_value',
                    's.total_sell_value'
                )
                ->whereRaw('s.snapshotted_at = (
                SELECT MAX(s2.snapshotted_at)
                FROM user_asset_snapshots s2
                WHERE s2.user_id = s.user_id
            )');
            // Step 2: snapshot peak (cao nhất từ trước đến nay) của mỗi user
            $peakAssets = DB::table('user_asset_snapshots')
                ->select('user_id', DB::raw('MAX(total_asset_value) as peakAssetValue'))
                ->groupBy('user_id');

            // Step 3: join users với current + peak snapshots
            $results = DB::table('users as u')
                ->where('u.type', '<>', 'bot')
                ->joinSub($currentAssets, 'c', 'u.id', '=', 'c.user_id')
                ->joinSub($peakAssets, 'p', 'u.id', '=', 'p.user_id')
                ->selectRaw('
            u.id as userId,
            u.uid,
            u.email,
            u.name,
            u.created_at,
            p.peakAssetValue,
            c.currency,
            c.total_buy,
            c.total_sell,
            c.total_buy_value,
            c.total_sell_value,
            c.currentAssetValue,
            (p.peakAssetValue - c.currentAssetValue) as reductionAmount,
            CASE 
                WHEN p.peakAssetValue > 0 
                THEN ROUND((p.peakAssetValue - c.currentAssetValue) / p.peakAssetValue * 100, 2) 
                ELSE 0 
            END as reductionPercent,
            CASE WHEN p.peakAssetValue > c.currentAssetValue THEN 1 ELSE 0 END as is_loser
        ')
                //->whereRaw('p.peakAssetValue > c.currentAssetValue') // chỉ những người đang lỗ
                ->orderByDesc('reductionPercent')
                ->get();

            // Step 4: cập nhật bảng losses_statistics_overview
            foreach ($results as $row) {
                $now = round(microtime(true) * 1000);
                $data = [
                    'uid' => $row->uid,
                    'name' => $row->name,
                    'email' => $row->email,
                    'register_date' => $row->created_at,
                    'currency' => $row->currency,
                    'total_buy' => $row->total_buy,
                    'total_sell' => $row->total_sell,
                    'total_buy_value' => $row->total_buy_value,
                    'total_sell_value' => $row->total_sell_value,
                    'peak_asset_value' => $row->peakAssetValue,
                    'current_asset_value' => $row->currentAssetValue,
                    'asset_reduction_amount' => $row->reductionAmount,
                    'asset_reduction_percent' => $row->reductionPercent,
                    'is_loser' => $row->is_loser,
                    'calculated_at' => $now,
                    // 'updated_at' => $now,
                ];
                LosersStatisticsOverview::updateOrCreate(
                    ['user_id' => $row->userId, 'currency' => $row->currency],
                    $data
                );
            }
        } catch (\Throwable $e) {
            Log::channel('spot_overview')->error('Error in [overview:top-loses] job', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
        
        $count = sizeof($results);
        $this->info("{$logTime} END-[{$this->signature}] {$this->description} =====counts: [{$count}]=====job completed===========\n");
    }
}
