<?php

namespace App\Http\Services;

use App\Consts;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PnlService
{
    private PriceService $priceService;

    public function __construct()
    {
        $this->priceService = new PriceService();
    }

    public function getPnlUser()
    {
        $userId = Auth::id();
        $date = Carbon::now()->format('Y-m-d');
        $initialBalance = $this->getInitialBalance($userId,$date);
        $currentAssetTotal = $this->getTotalBalanceMainAndSpot($userId);

        $transferHistories = $this->getTransferHistory($date, $userId);
        $pnlLast24h = $this->getLastPnl24hUser($userId);
        $amountTransferConvert = $this->convertTransferAmount($transferHistories[$userId] ?? []);

//        $transactions = $this->getTransaction(Carbon::now()->format('Y-m-d'), $userId);
//        $amount = $transactions[$userId]['amount'] ?? 0;
//        $fee = $transactions[$userId]['fee'] ?? 0;

        $amountSpotMain = $amountTransferConvert['amount_spot_main'];
        $amountMainSpot = $amountTransferConvert['amount_main_spot'];

        $todayNetTransfer = $this->calcTodayNetTransfer($amountSpotMain, $amountMainSpot);
        $pnl = $this->calcPnl($currentAssetTotal, $initialBalance, $todayNetTransfer);
        $pnlChange = '0';

        if (BigNumber::new($pnl)->comp(0) != 0) {
            $pnlChange = $this->calcPnlChange($pnl, $pnlLast24h);
        }

        return [
            'pnl' => $pnl,
            'pnl_change' => $pnlChange
        ];

    }

    public function getInitialBalance($userId, $date)
    {
        return DB::table('pnls')
            ->where('user_id', $userId)
            ->where(DB::raw("(DATE_FORMAT(created_at,'%Y-%m-%d'))"),$date)
            ->value('initial_balance') ?? 0;
    }

    public function getTotalBalanceMainAndSpot($userId)
    {
        $totalBalanceMain = 0;
        $totalBalanceSpot = 0;

        $userService = new UserService();
        $balanceUser = $userService->getUserAccounts($userId);
//
//        foreach ($balanceUser['main'] ?? [] as $key => $balanceMain) {
//            $priceUsd = $this->getUsdPrice($key);
//            $priceUsd = isset($priceUsd->price) && is_numeric($priceUsd->price) ? $priceUsd->price : 1;
//
//            $priceConvert = BigNumber::new($balanceMain->balance ?? 0)->mul($priceUsd)->toString();
//            $totalBalanceMain += $priceConvert;
//        }

        foreach ($balanceUser['spot'] ?? [] as $key => $balanceSpot) {
            $priceUsd = $this->getUsdPrice($key);
            $priceUsd = isset($priceUsd->price) && is_numeric($priceUsd->price) && $priceUsd->price > 0 ? $priceUsd->price : 1;

            $priceConvert = BigNumber::new($balanceSpot->balance ?? 0)->mul($priceUsd)->toString();
            $totalBalanceSpot += $priceConvert;
        }

//        return BigNumber::new($totalBalanceMain)->add($totalBalanceSpot)->toString();
        return BigNumber::new($totalBalanceSpot)->toString();
    }

    public function getUsdPrice($coin)
    {
        if ($coin == Consts::CURRENCY_USDT) {
            return (object)[
                'currency' => Consts::CURRENCY_USDT,
                'coin' => $coin,
                'price' => 1,
            ];
        }

        return $this->priceService->getCurrentPrice(Consts::CURRENCY_USDT, $coin);
    }

    public function getTransferHistory($date, $userId = null): array
    {
        $totalTransferGroupUser = [];
        $transferHistories = DB::table('transfer_history')
            ->select([
                'user_id',
                DB::raw('SUM(amount) as amount'),
                'source',
                'coin',
                'destination',
                'created_at',
            ])
            ->where(function ($query) use ($userId, $date) {
                return $query->when($userId, function ($query) use ($userId) {
                    return $query->where('user_id', $userId);
                })->where([
                    ['source', '=', Consts::TRANSFER_HISTORY_SPOT],
                    ['destination', '=', Consts::TRANSFER_HISTORY_MAIN],
                ])->where(DB::raw("(DATE_FORMAT(created_at,'%Y-%m-%d'))"), $date);
            })
            ->orWhere(function ($query) use ($userId, $date) {
                return $query->when($userId, function ($query) use ($userId) {
                    return $query->where('user_id', $userId);
                })->
                where([
                    ['source', '=', Consts::TRANSFER_HISTORY_MAIN],
                    ['destination', '=', Consts::TRANSFER_HISTORY_SPOT],
                ])
                    ->where(DB::raw("(DATE_FORMAT(created_at,'%Y-%m-%d'))"), $date);
            })
            ->groupBy('user_id', 'source', 'coin', 'destination')
            ->get();

        foreach ($transferHistories as $item_data) {
            $totalTransferGroupUser[$item_data->user_id][] = $item_data;
        }

        return $totalTransferGroupUser;
    }

    public function getLastPnl24h($userId)
    {
        return DB::table('pnls')
            ->where('user_id', $userId)
            ->where(DB::raw("(DATE_FORMAT(created_at,'%Y-%m-%d'))"), Carbon::yesterday()->format('Y-m-d'))
            ->value('pnl') ?? 0;
    }

    public function getLastPnl24hUser($userId)
    {
        return DB::table('pnls')
                ->where('user_id', $userId)
                ->where(DB::raw("(DATE_FORMAT(created_at,'%Y-%m-%d'))"), Carbon::now()->format('Y-m-d'))
                ->value('pnl') ?? 0;
    }

    public function calcTodayNetTransfer($amountSpotMain, $amountMainSpot, $amount = 0, $fee = 0)
    {
        return BigNumber::new($amount)
            ->add($amountMainSpot) // main->spot in
            ->sub($amountSpotMain) // spot -> main out
            ->toString();
    }

    public function calcPnl($currentAssetTotal, $totalInitBalance, $todayNetTransfer)
    {
        return BigNumber::new($currentAssetTotal)->sub($totalInitBalance)->sub($todayNetTransfer)->toString();
    }

    public function calcPnlChange($pnl, $pnlLast24h)
    {
        if (!$pnlLast24h) {
            return 0;
        }

        return BigNumber::new($pnl)->sub($pnlLast24h)->div($pnlLast24h)->mul(100)->toString();
    }

    public function getTransaction($date, $userId = null): array
    {
        $transactionGroupUsers = [];

        $transactions = DB::table('transactions')
            ->select([
                'user_id',
                'amount',
                'currency',
                'fee',
                'transaction_date',
            ])
            ->where('status', 'success')
            ->when($userId, function ($query) use ($userId) {
                return $query->where('user_id', $userId);
            })
            ->where('transaction_date', $date)
            ->get();

        foreach ($transactions as $transaction) {
            if (!isset($transactionGroupUsers[$transaction->user_id])) {
                $priceUsd = $this->getUsdPrice($transaction->currency);
                $priceUsd = is_null($priceUsd) ? 0 : $priceUsd->price;

                $transactionGroupUsers[$transaction->user_id] = [
                    'amount' => BigNumber::new($transaction->amount)->mul($priceUsd)->toString(),
                    'fee' => BigNumber::new($transaction->fee)->mul($priceUsd)->toString()
                ];
            } else {
                $priceUsd = $this->getUsdPrice($transaction->currency);
                $priceUsd = is_null($priceUsd) ? 0 : $priceUsd->price;

                $amountConvert = BigNumber::new($transaction->amount)->mul($priceUsd)->toString();
                $feeConvert = BigNumber::new($transaction->fee)->mul($priceUsd)->toString();

                $amount = BigNumber::new($transactionGroupUsers[$transaction->user_id]['amount'])->add($amountConvert)->toString();
                $fee = BigNumber::new($transactionGroupUsers[$transaction->user_id]['fee'])->add($feeConvert)->toString();;

                $transactionGroupUsers[$transaction->user_id]['amount'] = $amount;
                $transactionGroupUsers[$transaction->user_id]['fee'] = $fee;
            }

        }
        return $transactionGroupUsers;
    }

    public function convertTransferAmount($transfers)
    {
        $amountMainSpot = 0;
        $amountSpotMain = 0;
        foreach ($transfers as $transfer) {
            if ($transfer->source == Consts::TRANSFER_HISTORY_MAIN && $transfer->destination == Consts::TRANSFER_HISTORY_SPOT) {
                $priceUsd = $this->getUsdPrice($transfer->coin);
                $priceUsd = isset($priceUsd->price) && is_numeric($priceUsd->price) ? $priceUsd->price : 1;

                $amountMainSpot += BigNumber::new($transfer->amount)->mul($priceUsd)->toString();
            }

            if ($transfer->source == Consts::TRANSFER_HISTORY_SPOT && $transfer->destination == Consts::TRANSFER_HISTORY_MAIN) {
                $priceUsd = $this->getUsdPrice($transfer->coin);
                $priceUsd = isset($priceUsd->price) && is_numeric($priceUsd->price) ? $priceUsd->price : 1;

                $amountSpotMain += BigNumber::new($transfer->amount)->mul($priceUsd)->toString();
            }
        }

        return [
            'amount_spot_main' => $amountSpotMain,
            'amount_main_spot' => $amountMainSpot
        ];
    }

    public function getTransferHistoryByHours(array $date, $userId = null): array
    {
        $totalTransferGroupUser = [];
        $transferHistories = DB::table('transfer_history')
            ->select([
                'user_id',
                DB::raw('SUM(amount) as amount'),
                'source',
                'coin',
                'destination',
            ])
            ->where(function ($query) use ($userId, $date) {
                return $query->when($userId, function ($query) use ($userId) {
                    return $query->where('user_id', $userId);
                })->where([
                    ['source', '=', Consts::TRANSFER_HISTORY_SPOT],
                    ['destination', '=', Consts::TRANSFER_HISTORY_MAIN],
                ])->whereBetween(DB::raw("created_at"), [$date['dateStart'], $date['dateEnd']]);
            })
            ->orWhere(function ($query) use ($userId, $date) {
                return $query->when($userId, function ($query) use ($userId) {
                    return $query->where('user_id', $userId);
                })->
                where([
                    ['source', '=', Consts::TRANSFER_HISTORY_MAIN],
                    ['destination', '=', Consts::TRANSFER_HISTORY_SPOT],
                ])->whereBetween(DB::raw("created_at"), [$date['dateStart'], $date['dateEnd']]);
            })
            ->groupBy('user_id', 'source', 'coin', 'destination')
            ->get();

        foreach ($transferHistories as $item_data) {
            $totalTransferGroupUser[$item_data->user_id][] = $item_data;
        }

        return $totalTransferGroupUser;
    }
}
