<?php

namespace App\Console\Commands;

use App\Consts;
use App\Models\OrderTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\AMALNetStatistic;
use App\Utils\BigNumber;
use Transaction\Models\Transaction;

class CreateAMALNetHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:amal_net_history';
     /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create AMAL Net History';

     /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $trades = OrderTransaction::where('coin', Consts::CURRENCY_AMAL)
            ->orWhere('buy_fee_amal', '>', 0)
            ->orWhere('sell_fee_amal', '>', 0)
            ->get();
        foreach ($trades as $trade) {
            $this->processTrade($trade);
        }
         $transactions = Transaction::where('currency', Consts::CURRENCY_AMAL)
            ->where('amount', '<', 0)
            ->get();
        foreach ($transactions as $transaction) {
            $totalOut = BigNumber::new($transaction->amount)->add($transaction->fee)->mul(-1)->toString();
            $this->updateRecord($transaction->user_id, $transaction->transaction_date, $totalOut, 0);
        }
    }
    public function processTrade($trade)
    {
        $buyerId = $trade->buyer_id;
        $sellerId = $trade->seller_id;
        $totalInForBuyer = 0;
        $totalOutForBuyer = 0;

        if ($trade->coin == Consts::CURRENCY_AMAL) {
            $totalInForBuyer = $trade->buy_fee_amal ? BigNumber::new($trade->quantity)->sub($trade->buy_fee_amal)->toString() : BigNumber::new($trade->quantity)->sub($trade->buy_fee)->toString();
            $totalOutForSeller = $trade->sell_fee_amal ? BigNumber::new($trade->quantity)->add($trade->sell_fee_amal)->toString() : $trade->quantity;
        } else {
            $totalOutForBuyer = $trade->buy_fee_amal ? BigNumber::new($trade->buy_fee_amal) : 0;
            $totalOutForSeller = $trade->sell_fee_amal ? BigNumber::new($trade->sell_fee_amal) : 0;
        }
        $this->updateRecord($buyerId, $trade->executed_date, $totalOutForBuyer, $totalInForBuyer);
        $this->updateRecord($sellerId, $trade->executed_date, $totalOutForSeller, 0);
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
}
