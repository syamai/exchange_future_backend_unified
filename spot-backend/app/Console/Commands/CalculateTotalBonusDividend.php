<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\App;
use App\Consts;
use App\Http\Services\MasterdataService;
use App\Models\AutoDividendHistory;
use App\Models\ManualDividendHistory;
use App\Models\DividendCashbackHistory;
use App\Models\DividendTotalBonus;
use App\Utils\BigNumber;
use Illuminate\Console\Command;

class CalculateTotalBonusDividend extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:calculate_total_bonus_dividend';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate Total Bonus Dividend';





    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $records = MasterdataService::getOneTable('coins');
        foreach ($records as $record) {
            $totalBonus = $this->calculateTotalBonus($record->coin);
            DividendTotalBonus::where('coin', $record->coin)
                ->update([
                    'total_bonus' => $totalBonus,
                ]);
        }
    }

    public function calculateTotalBonus($coin)
    {
        $sum = 0;
        $autodividendrecords = AutoDividendHistory::where('bonus_currency', $coin)->sum('bonus_amount');
        $sum = BigNumber::new($sum)->add($autodividendrecords);

        $manualDividendrecords = ManualDividendHistory::where('bonus_currency', $coin)->sum('bonus_amount');
        $sum = BigNumber::new($sum)->add($manualDividendrecords);

        if ($coin == Consts::CURRENCY_AMAL) {
            $cashbackHistory = DividendCashbackHistory::sum('amount');
            $sum = BigNumber::new($sum)->sub($cashbackHistory);
        }
        return $sum;
    }
}
