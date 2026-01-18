<?php

namespace App\Console\Commands;

use App\Models\JobCheckpoints;
use App\Models\User;
use App\Models\UserAssetCommissionRates;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateUserCommissionRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:commission-rates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update spot commission & rates user owner';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $logTime = Carbon::now()->format('Y-m-d H:i:s');
        $this->info("{$logTime} START-[{$this->signature}] {$this->description} =====================\n");
        
        $jobName = 'calculate_user_commission_rates';
        $now = round(microtime(true) * 1000);
        $chunkSize = config('constants.chunk_limit_checkpoint');

        $this->info("Calculating user commission rates (chunked {$chunkSize} records)...");
        try {
            $lastProcessedId = JobCheckpoints::jobName($jobName)->value('last_calculated_at') ?? 0;

            // Subquery: user_rates
            $user_rates = DB::table('user_rates')->select('id', 'commission_rate');

            // Subquery: referrer_histories mới (chunk theo id)
            $history_ref = DB::table('referrer_histories')
                ->select(
                    'user_id',
                    'coin',
                    'type',
                    DB::raw('SUM(amount) as amount'),
                    DB::raw('SUM(usdt_value) as usdt_value'),
                    DB::raw('MAX(id) as max_id') // dùng để lấy lastProcessedId
                )
                ->where('id', '>', $lastProcessedId)
                ->groupBy('user_id', 'coin', 'type');

            // Main query: join users + user_rates + history_ref
            $commission_rates = DB::table('users as u')
                ->where('u.type', '<>', 'bot')
                ->joinSub(
                    $user_rates,
                    'ur',
                    fn($join) =>
                    $join->on('ur.id', '=', 'u.id')
                )
                ->joinSub(
                    $history_ref,
                    'r',
                    fn($join) =>
                    $join->on('u.id', '=', 'r.user_id')
                )
                ->select(
                    'u.id',
                    'r.coin',
                    'ur.commission_rate',
                    DB::raw("SUM(CASE WHEN r.type = 'spot' THEN r.amount ELSE 0 END) as total_spot_commission_amount"),
                    DB::raw("SUM(CASE WHEN r.type = 'future' THEN r.amount ELSE 0 END) as total_future_commission_amount"),
                    DB::raw("SUM(CASE WHEN r.type = 'spot' THEN r.usdt_value ELSE 0 END) as total_spot_commission_usdt_value"),
                    DB::raw("SUM(CASE WHEN r.type = 'future' THEN r.usdt_value ELSE 0 END) as total_future_commission_usdt_value"),
                    DB::raw("MAX(r.max_id) as max_id")
                )
                ->groupBy('u.id', 'r.coin', 'ur.commission_rate')
                ->orderBy('max_id')
                ->limit($chunkSize)
                ->get();

            if ($commission_rates->isEmpty()) {
                $this->info("{$logTime} END-[{$this->signature}] {$this->description} | No new commission data to process. \n");
                return;
            }

            $newLastId = $commission_rates->max('max_id');
            $count = $this->processCommissionBatch($commission_rates, $now);

            // Cập nhật checkpoint
            JobCheckpoints::updateOrCreate(
                ['job' => $jobName],
                [
                    'last_calculated_at' => $newLastId,
                    'updated_at' => $now,
                ]
            );
        } catch (\Throwable $e) {
            Log::channel('spot_overview')->error('Error in [spot:commission-rates] job', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $this->info("{$logTime} END-[{$this->signature}] {$this->description} ====Counts: [{$count}] | jobName:[{$jobName}] - {$newLastId} ======job completed===========\n");
    }

    protected function processCommissionBatch($rows, $now): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $record = UserAssetCommissionRates::firstOrNew([
                'user_id' => $row->id,
                'coin' => $row->coin,
            ]);

            $record->commission_rate = $row->commission_rate;
            $record->total_spot_commission_amount = $row->total_spot_commission_amount;
            $record->total_future_commission_amount = $row->total_future_commission_amount;
            $record->total_spot_commission_usdt_value = $row->total_spot_commission_usdt_value;
            $record->total_future_commission_usdt_value = $row->total_future_commission_usdt_value;
            $record->reported_at = $now;

            if (!$record->exists) {
                $record->created_at = $now;
            }

            $record->save();
            $count++;
        }

        return $count;
    }
}
