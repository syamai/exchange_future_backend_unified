<?php

namespace App\Console\Commands;

use App\Consts;
use App\Models\GainsStatisticsOverview;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculateTopGainsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overview:top-gains';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate and store top gains snapshot for all users';

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
            // Subquery: Lấy giá hiện tại theo coin
            $latestPrices = DB::table('prices as p1')
                ->select('p1.coin', 'p1.price')
                ->whereRaw('p1.created_at = (
            SELECT MAX(p2.created_at)
            FROM prices p2
            WHERE p2.coin = p1.coin AND p2.currency = ?
        )', [Consts::CURRENCY_USDT]);

            // Subquery: tổng holdings (buy - sell) * giá hiện tại, theo user + currency
            $holdings = DB::table('user_coin_holdings as h')
                ->joinSub(
                    $latestPrices,
                    'p',
                    fn($join) => $join->on('h.coin', '=', 'p.coin')
                )
                ->selectRaw('
            h.user_id,
            h.currency,
            SUM(h.total_buy) as total_buy,
            SUM(h.total_sell) as total_sell,
            SUM(h.total_buy_value) as total_buy_value,
            SUM(h.total_sell_value) as total_sell_value,
            SUM((h.total_buy - h.total_sell) * p.price) as totalAssetValue
        ')
                ->groupBy('h.user_id', 'h.currency');

            // Subquery: tổng tiền nạp của user theo từng currency
            $deposits = DB::table('transactions as t')
                ->joinSub(
                    $latestPrices,
                    'p',
                    fn($join) => $join->on('t.currency', '=', 'p.coin')
                )
                ->where('t.amount', '>', 0)
                ->where('t.status', Consts::TRANSACTION_STATUS_SUCCESS)
                ->selectRaw('
            t.user_id,
            t.currency,
            SUM(t.amount * p.price) as totalDeposit
        ')
                ->groupBy('t.user_id', 't.currency');

            // Truy vấn cuối: tổng hợp user, tính net gain và gain %
            $results = DB::table('users as u')
                ->where('u.type', '<>', 'bot')
                ->joinSub($holdings, 'a', 'u.id', '=', 'a.user_id')
                ->leftJoinSub($deposits, 'd', function ($join) {
                    $join->on('u.id', '=', 'd.user_id')
                        ->on('a.currency', '=', 'd.currency');
                })
                ->selectRaw('
            u.id as userId,
            u.uid,
            u.email,
            u.name,
            u.created_at,
            a.currency,
            a.totalAssetValue,
            a.total_buy,
            a.total_sell,
            a.total_buy_value,
            a.total_sell_value,
            d.totalDeposit,
            (a.totalAssetValue - COALESCE(d.totalDeposit, 0)) as netGain,
            CASE 
                WHEN d.totalDeposit > 0 THEN ROUND((a.totalAssetValue - d.totalDeposit) / d.totalDeposit * 100, 2)
                ELSE 0
            END as gainPercent
        ')
                ->orderByDesc('gainPercent')
                ->get();

            // Ghi lại vào bảng thống kê theo user + currency
            foreach ($results as $row) {
                GainsStatisticsOverview::updateOrCreate(
                    ['user_id' => $row->userId, 'currency' => $row->currency], // 🔁 dùng cặp khóa
                    [
                        'uid' => $row->uid,
                        'name' => $row->name,
                        'email' => $row->email,
                        'register_date' => $row->created_at,
                        'total_buy' => $row->total_buy,
                        'total_sell' => $row->total_sell,
                        'total_buy_value' => $row->total_buy_value,
                        'total_sell_value' => $row->total_sell_value,
                        'total_asset_value' => $row->totalAssetValue,
                        'net_gain' => $row->netGain,
                        'gain_percent' => $row->gainPercent,
                        'total_deposit' => $row->totalDeposit,
                        'calculated_at' => round(microtime(true) * 1000),
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::channel('spot_overview')->error('Error in [overview:top-gains] job', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $this->info("{$logTime} END-[{$this->signature}] {$this->description} ==========job completed===========\n");
    }
}
