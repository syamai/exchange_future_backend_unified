<?php

namespace App\Console\Commands;

use App\Models\AMALNetStatistic;
use App\Consts;
use App\Utils\BigNumber;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CalculateAMALNet extends SpotTradeBaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:update_amal_net';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update AMAL Net Holding';
    protected $process;



    protected function processTrade($trade)
    {
        $buyerId = $trade->buyer_id;
        $sellerId = $trade->seller_id;
        $totalInForBuyer = 0;
        $totalOutForBuyer = 0;
        $totalOutForSeller = 0;
        if ($trade->coin == Consts::CURRENCY_AMAL) {
            $totalInForBuyer = $trade->buy_fee_amal ? BigNumber::new($trade->quantity)->sub($trade->buy_fee_amal)->toString() : BigNumber::new($trade->quantity)->sub($trade->buy_fee)->toString();
            $totalOutForSeller = $trade->sell_fee_amal ? BigNumber::new($trade->quantity)->add($trade->sell_fee_amal)->toString() : $trade->quantity;
        } else {
            $totalOutForBuyer = $trade->buy_fee_amal ? BigNumber::new($trade->buy_fee_amal) : 0;
            $totalOutForSeller = $trade->sell_fee_amal ? BigNumber::new($trade->sell_fee_amal) : 0;
        }

        $this->updateRecord($buyerId, Carbon::now()->toDateString(), $totalOutForBuyer, $totalInForBuyer);
        $this->updateRecord($sellerId, Carbon::now()->toDateString(), $totalOutForSeller, 0);
    }

    public function updateRecord($userId, $date, $totalOut, $totalIn)
    {
        $record = AMALNetStatistic::where('user_id', $userId)
            ->where('statistic_date', $date)
            ->first();
        if (!$record) {
            return AMALNetStatistic::create([
                'user_id' => $userId,
                'statistic_date' => $date,
                'amal_in' => $totalIn,
                'amal_out' => $totalOut
            ]);
        }
        return AMALNetStatistic::where('user_id', $userId)
            ->where('statistic_date', $date)
            ->update([
                'amal_in' => DB::raw('amal_in + ' . $totalIn),
                'amal_out' => DB::raw('amal_out + ' . $totalOut)
            ]);
    }

    protected function getProcessKey(): string
    {
        return 'update_amal_net_holding';
    }
}
