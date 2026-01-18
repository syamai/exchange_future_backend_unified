<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\PnlService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculatePnl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculate:pnl {--test}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command calculate pnl';

    public function handle()
    {
        $dataPnl = $this->makeDataPnl();
        $this->insertPnl($dataPnl);
    }

    private function makeDataPnl(): array
    {
        $dataPnl = [];
        $pnlService = new PnlService();
        $date = Carbon::yesterday()->format('Y-m-d');
//        $transactions = $pnlService->getTransaction($date);
        $users = User::query()
            ->where('type','!=', Consts::USER_TYPE_BOT)
            ->where('status',Consts::USER_ACTIVE)
            ->select('id')
            ->get();


        foreach ($users as $user) {
            $initialBalance = $pnlService->getInitialBalance($user->id,$date);
            $currentAssetTotal = $pnlService->getTotalBalanceMainAndSpot($user->id);
            $pnlLast24h = $pnlService->getLastPnl24h($user->id);
            $transferHistories = $pnlService->getTransferHistory($date, $user->id);
            $amountTransferConvert = $pnlService->convertTransferAmount($transferHistories[$user->id] ?? []);

            $todayNetTransfer = $pnlService->calcTodayNetTransfer($amountTransferConvert['amount_spot_main'], $amountTransferConvert['amount_main_spot']);
            $pnl = $pnlService->calcPnl($currentAssetTotal, $initialBalance, $todayNetTransfer);
            $pnlChange = $pnlService->calcPnlChange($pnl, $pnlLast24h);

            $this->info("== Make data pnl
            userId: $user->id
            currentAssetTotal: $currentAssetTotal
            initialBalance: $initialBalance
            todayNetTransfer:$todayNetTransfer
            pnl: $pnl
            pnlLast24h: $pnlLast24h
            pnlChange: $pnlChange
            ");

            $dataPnl[] = [
                'user_id' => $user->id,
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
