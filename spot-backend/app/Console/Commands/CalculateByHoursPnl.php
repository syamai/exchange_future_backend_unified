<?php

namespace App\Console\Commands;

use App\Http\Services\PnlService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateByHoursPnl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculate:pnl-by-hour {--dateStart=} {--dateEnd=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command calculate pnl by hours';


    public function handle()
    {
        $dataPnl = $this->makeDataPnl();
        $this->insertPnl($dataPnl);
    }

    private function makeDataPnl(): array
    {
        $dataPnl = [];
        $pnlService = new PnlService();
        $dateStart = Carbon::parse($this->option('dateStart'))->format('Y-m-d H:i:s');
        $dateEnd = Carbon::parse($this->option('dateEnd'))->format('Y-m-d H:i:s');
//        $transactions = $pnlService->getTransaction($date);
        $date = [
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd
        ];
        $transferHistories = $pnlService->getTransferHistoryByHours($date);

        foreach ($transferHistories as $key => $transferHistory) {
            $initialBalance = $pnlService->getInitialBalance($key);
            $currentAssetTotal = $pnlService->getTotalBalanceMainAndSpot($key);
            $pnlLast24h = $pnlService->getLastPnl24h($key);
            $amountTransferConvert = $pnlService->convertTransferAmount($transferHistory);

            $todayNetTransfer = $pnlService->calcTodayNetTransfer($amountTransferConvert['amount_spot_main'], $amountTransferConvert['amount_main_spot']);
            $pnl = $pnlService->calcPnl($currentAssetTotal, $initialBalance, $todayNetTransfer);
            $pnlChange = $pnlService->calcPnlChange($pnl, $pnlLast24h);

            $this->info("== Make data pnl
            userId: $key
            currentAssetTotal: $currentAssetTotal
            initialBalance: $initialBalance
            todayNetTransfer:$todayNetTransfer
            pnl: $pnl
            pnlLast24h: $pnlLast24h
            pnlChange: $pnlChange
            ");

            $dataPnl[] = [
                'user_id' => $key,
                'initial_balance' => $currentAssetTotal,
                'pnl' => $pnl,
                'pnl_change' => $pnlChange,
                'created_at' => now()
            ];
        }

        return $dataPnl;
    }

    private function insertPnl($dataPnl)
    {
        if (!$dataPnl) {
            $this->info('=== Empty data insert pnl');
        }

        DB::table('pnls')->insert($dataPnl);
    }
}
