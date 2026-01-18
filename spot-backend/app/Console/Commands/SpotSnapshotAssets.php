<?php

namespace App\Console\Commands;

use App\Consts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SpotSnapshotAssets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:snapshot_assets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Snapshot current asset value of each user from holdings';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Creating asset snapshots from current holdings...');

        $now = round(microtime(true) * 1000);

        // 1. Lấy giá mới nhất theo coin
        $latestPrices = DB::table('prices as p1')
            ->select('p1.coin', 'p1.price')
            ->whereRaw('p1.created_at = (
                SELECT MAX(p2.created_at)
                FROM prices p2
                WHERE p2.coin = p1.coin AND p2.currency = ?
            )', [Consts::CURRENCY_USDT]);

        // 2. Lấy holdings từng user + currency, quy đổi giá trị hiện tại
        $snapshots = DB::table('user_coin_holdings as h')
            ->joinSub(
                $latestPrices,
                'p',
                fn($join) =>
                $join->on('h.coin', '=', 'p.coin')
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
            ->groupBy('h.user_id', 'h.currency')
            ->get();

        // 3. Chuẩn bị dữ liệu để insert
        $insertData = $snapshots->map(function ($row) use ($now) {
            return [
                'user_id' => $row->user_id,
                'currency' => $row->currency,
                'total_buy' => $row->total_buy,
                'total_sell' => $row->total_sell,
                'total_buy_value' => $row->total_buy_value,
                'total_sell_value' => $row->total_sell_value,
                'total_asset_value' => $row->totalAssetValue,
                'snapshotted_at' => $now,
                'created_at' => $now,      // chỉ dùng khi insert mới
                'updated_at' => $now,
            ];
        })->toArray();

        // 4. Ghi vào bảng snapshot bằng upsert
        DB::table('user_asset_snapshots')->insert($insertData);

        $this->info('Snapshot complete: ' . count($snapshots) . ' records inserted.');
    }
}
