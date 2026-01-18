<?php
namespace App\Http\Services;

use App\Consts;
use Illuminate\Support\Facades\DB;
use App\Utils\BigNumber;
use App\Utils;

class TransactionFeeService
{
    const DISCOUNTED_PERCENT = 0.5; // 50%
    private PayFeeByAmalService $payFeeByAmalService;
    private PriceService $priceService;

    public function __construct()
    {
        $this->priceService = new PriceService();
        $this->payFeeByAmalService = new PayFeeByAmalService();
    }

    public function updateTransactionFee($orderTransaction, $userId, $totalTransactionFee, $order): bool
    {
        $statusEnable = $this->payFeeByAmalService->checkEnableFeeAmal($userId);
        if (!$statusEnable) {
            return false;
        }

        $amalFee = $this->convertFeeToAMAL($orderTransaction, $order, $totalTransactionFee);
        if (!$amalFee) {    // Not pay by AMAL
            return false;
        }
        // Calculate discount 50% AMAL ($amalFeeDiscounted)
        $amalFeeDiscounted = BigNumber::new($amalFee)->mul(self::DISCOUNTED_PERCENT)->toString();
        $rsult = $this->payFeeByAmalService->payAmalFeeFollowTypeWallet($userId, $amalFeeDiscounted, null, Consts::DB_CONNECTION_MASTER);
        if (!$rsult) {
            return false;
        }

        logger("tradinglog updatetransactionfee da thuc hien tot tai user " .$userId);
        // Add (Revert) Coin Balance $totalTransactionFee to spot_{$coin}_accounts
        $targetCoin = $this->getTargetCoin($orderTransaction, $order);
        $table = 'spot_' . $targetCoin . '_accounts';
        $balanceRecord = DB::connection('master')->table($table)
            ->where('id', $userId)
            ->lockForUpdate()
            ->first();
        DB::connection('master')->table($table)
            ->where('id', $userId)
            ->update([
                'balance' => BigNumber::new($balanceRecord->balance)->add($totalTransactionFee),
                'available_balance' => BigNumber::new($balanceRecord->available_balance)->add($totalTransactionFee),
            ]);
        $this->saveAmalFeeTo($orderTransaction, $order->trade_type, $amalFeeDiscounted);
        return true;
    }

    //convert from $coin to $currency with amount is $amount
    public function convertFeeToAMAL($transaction, $order, $fee)
    {
        $amalPrice = $this->getPriceInUsdt(Consts::CURRENCY_AMAL, $transaction);

        if (!$amalPrice) {
            return false;
        }

        logger('tradinglog convertFeeToAMAL 1');
        $sourceCurrency = $this->getTargetCoin($transaction, $order);

        if ($sourceCurrency == Consts::CURRENCY_USDT) {
            $sourcePrice = "1";
        } else {
            $sourcePrice = $this->getPriceInUsdt($sourceCurrency, $transaction);
        }
        logger('tradinglog convertFeeToAMAL 2'.$sourcePrice);

        if (!$sourcePrice) {
            return false;
        }

        logger('tradinglog convertFeeToAMAL 3');
        return BigNumber::new($sourcePrice)->div($amalPrice)->mul($fee)->toString();
    }

    public function getTargetCoin($transaction, $order)
    {
        return $order->trade_type == Consts::ORDER_TRADE_TYPE_BUY ? $transaction->coin : $transaction->currency;
    }

    public function getPriceInUsdt($currency, $transaction)
    {
        logger('tradinglog getPriceInUsdt 0');
        if ($currency == Consts::CURRENCY_USD) {
            logger('tradinglog getPriceInUsdt 1');
            return 1;
        }

        if ($transaction->currency == Consts::CURRENCY_USDT && $transaction->coin == $currency) {
            logger('tradinglog getPriceInUsdt 2'.$transaction->currency);
            return $transaction->price;
        }

        $price = $this->priceService->getCurrentPrice(Consts::CURRENCY_USDT, $currency, false);
        if (!isset($price) || $price->created_at < Utils::previous24hInMillis()) {
            logger('tradinglog getPriceInUsdt 3');
            return 0;
        }

        logger('tradinglog getPriceInUsdt final');
        return $price->price;
    }

    public function saveAmalFeeTo($orderTransaction, $tradeType, $amalFeeDiscounted): int
    {
        if ($tradeType == Consts::ORDER_TRADE_TYPE_BUY) {
            $type = Consts::ORDER_TRADE_TYPE_BUY;
        } else {
            $type = Consts::ORDER_TRADE_TYPE_SELL;
        }

        $res = DB::connection('master')->table('order_transactions')
            ->where('id', $orderTransaction->id)
            ->update([
                $type.'_fee_amal' => $amalFeeDiscounted,
            ]);

        return $res;
    }
}
