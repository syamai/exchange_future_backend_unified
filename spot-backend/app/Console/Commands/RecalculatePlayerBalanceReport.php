<?php

namespace App\Console\Commands;

use App\Consts;
use App\Models\PlayerRealBalanceReport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class RecalculatePlayerBalanceReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:player-realbalance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Player real balance report';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $logTime = Carbon::now()->format('Y-m-d H:i:s');
        $this->info("{$logTime} START-[{$this->signature}] {$this->description} =====================\n");
        
        $now = round(microtime(true) * 1000); // millisecond timestamp
        $count = 0;
        
        try {
            $latestPrices = DB::table('prices as p1')
                ->select('p1.coin', 'p1.price')
                ->where('p1.currency', Consts::CURRENCY_USDT)
                ->whereRaw('p1.created_at = (
            SELECT MAX(p2.created_at)
            FROM prices p2
            WHERE p2.coin = p1.coin AND p2.currency = ?
        )', [Consts::CURRENCY_USDT]);

            $transactions = DB::table('user_asset_transactions')
                ->select(
                    'user_id',
                    DB::raw("SUM(CASE WHEN category = 'deposit_success' THEN deposit_value ELSE 0 END) as total_deposit_value"),
                    DB::raw("SUM(CASE WHEN category = 'withdraw_success' THEN withdraw_value ELSE 0 END) as total_withdraw_value"),
                    DB::raw("SUM(CASE WHEN category = 'withdraw_pending' THEN pending_withdraw_value ELSE 0 END) as total_withdraw_pending_value"),
                )
                ->groupBy('user_id');

            $commission_rates = DB::table('user_asset_commission_rates')
                ->select(
                    'user_id',
                    'commission_rate as fee_rebates_percent',
                    DB::raw('SUM(total_spot_commission_usdt_value) as fee_rebates_value')
                )
                ->groupBy('user_id');

            $holdingsWithPrice = DB::table('user_coin_holdings as h')
                ->joinSub($latestPrices, 'latest_prices', function ($join) {
                    $join->on('h.coin', '=', 'latest_prices.coin');
                })
                ->select(
                    'h.user_id',
                    DB::raw('SUM((h.total_buy - h.total_sell) * latest_prices.price) as current_position'),
                    DB::raw('SUM( h.total_buy_value + h.total_sell_value ) as total_volume'),
                    DB::raw('SUM( h.total_fees_paid ) as total_fees_paid')
                )
                ->groupBy('h.user_id');

            $pending_posistions = DB::table('user_coin_pendings')
                ->select(
                    'user_id',
                    DB::raw('SUM( pending_buy_value + pending_sell_value ) as pending_position')
                )
                ->groupBy('user_id');

            $closing_balance = DB::table('user_asset_snapshots as s1')
                ->select('s1.user_id', DB::raw('s1.total_asset_value as closing_balance'))
                ->join(DB::raw('(
                    SELECT user_id, MAX(snapshotted_at) as latest_updated_at
                    FROM user_asset_snapshots
                    GROUP BY user_id
                ) as latest'), function ($join) {
                    $join->on('s1.user_id', '=', 'latest.user_id')
                        ->on('s1.snapshotted_at', '=', 'latest.latest_updated_at');
                });

            $data = DB::table('users as u')
                ->where('u.type', '<>', 'bot')
                ->leftJoinSub($transactions, 't', function ($join) {
                    $join->on('u.id', '=', 't.user_id');
                })
                ->leftJoinSub($commission_rates, 'c', function ($join) {
                    $join->on('u.id', '=', 'c.user_id');
                })
                ->leftJoinSub($holdingsWithPrice, 'h', function ($join) {
                    $join->on('u.id', '=', 'h.user_id');
                })
                ->leftJoinSub($pending_posistions, 'p', function ($join) {
                    $join->on('u.id', '=', 'p.user_id');
                })
                ->leftJoinSub($closing_balance, 'b', function ($join) {
                    $join->on('u.id', '=', 'b.user_id');
                })
                ->select(
                    'u.id as user_id',
                    'u.uid',
                    'u.last_login_at',
                    DB::raw('COALESCE(h.current_position, 0) as current_position'),
                    DB::raw('COALESCE(h.total_volume, 0) as total_volume'),
                    DB::raw('COALESCE(t.total_deposit_value, 0) as total_deposit'),
                    DB::raw('COALESCE(t.total_withdraw_value, 0) as total_withdraw'),
                    DB::raw('COALESCE(t.total_withdraw_pending_value, 0) as pending_withdraw'),
                    DB::raw('COALESCE(b.closing_balance, 0) as closing_balance'),
                    DB::raw('COALESCE(p.pending_position, 0) as pending_position'),
                    DB::raw('COALESCE(c.fee_rebates_percent, 0) as fee_rebates_percent'),
                    DB::raw('COALESCE(h.total_fees_paid, 0) as total_fees_paid'),
                    DB::raw('COALESCE(c.fee_rebates_value, 0) as fee_rebates_value'),

                    //Tính spot_profit + roi:
                    DB::raw('
                    (
                        COALESCE(b.closing_balance, 0) +
                        COALESCE(t.total_withdraw_value, 0) +
                        COALESCE(t.total_withdraw_pending_value, 0) -
                        COALESCE(t.total_deposit_value, 0)
                    ) as spot_profit
                '),
                    DB::raw('
                    CASE 
                        WHEN COALESCE(t.total_deposit_value, 0) = 0 THEN 0
                        ELSE ROUND((
                            (
                                COALESCE(b.closing_balance, 0) +
                                COALESCE(t.total_withdraw_value, 0) +
                                COALESCE(t.total_withdraw_pending_value, 0) -
                                COALESCE(t.total_deposit_value, 0)
                            ) / t.total_deposit_value
                        ) * 100, 2)
                    END as spot_roi
                ')
                )
                ->get();

            // dd($data);

            foreach ($data as $u) {
                $report = PlayerRealBalanceReport::firstOrNew([
                    'user_id' => $u->user_id,
                    'uid' => $u->uid
                ]);

                $report->total_deposit = $u->total_deposit;
                $report->total_withdraw = $u->total_withdraw;
                $report->pending_withdraw = $u->pending_withdraw;
                $report->closing_balance = $u->closing_balance;
                $report->total_volume = $u->total_volume;
                $report->current_position = $u->current_position;
                $report->pending_position = $u->pending_position;
                $report->profit = $u->spot_profit;
                $report->roi = $u->spot_roi;
                $report->total_fees_paid = $u->total_fees_paid;
                $report->fee_rebates_percent = $u->fee_rebates_percent;
                $report->fee_rebates_value = $u->fee_rebates_value;
                $report->last_login_at = $u->last_login_at;

                $report->reported_at = $now;
                // $report->updated_at = $now;

                if (!$report->exists) {
                    $report->created_at = $now;
                }

                $report->save();
                $count++;
            }
        } catch (\Throwable $e) {
            Log::channel('spot_overview')->error('Error in [report:player-realbalance] job', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $this->info("{$logTime} END- [{$this->signature}] {$this->description} ======[counts: $count]====job completed===========\n");
    }
}
