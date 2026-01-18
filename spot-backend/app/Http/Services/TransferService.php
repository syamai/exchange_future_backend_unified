<?php

namespace App\Http\Services;

use App\Consts;
use App\Events\MainBalanceUpdated;
use App\Models\TransferHistory;
use App\Models\User;
use App\Utils;
use App\Utils\BigNumber;
use Carbon\Carbon;
use ErrorException;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TransferService
{
    public function __construct()
    {

    }

    private function getMainBalanceTranfer()
    {
        $isSpotMainBalance = env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false);
        if ($isSpotMainBalance) {
            return Consts::TYPE_EXCHANGE_BALANCE;
        }

        return Consts::TYPE_MAIN_BALANCE;
    }

    public function transfer($request)
    {
        $userId = Auth::id();
//        $token = $request->header('authorization');
//        $futureBaseUrl = env('FUTURE_API_URL');
//        $futureAccessTokenUrl = $futureBaseUrl . '/api/v1/access-token';
//        $futureDepositUrl = $futureBaseUrl . '/api/v1/account/deposit';
        $mainBalance = $this->getMainBalanceTranfer();
        if ($mainBalance == Consts::TYPE_EXCHANGE_BALANCE && $request->from === Consts::TYPE_MAIN_BALANCE) {
            $request->from = Consts::TYPE_EXCHANGE_BALANCE;
        }
        if ($request->from === $mainBalance && $request->to === Consts::TYPE_FUTURE_BALANCE) {
            $fromTable = $this->getTableType($mainBalance, $request->asset);
            try {
                $balance = DB::table($fromTable)->where(['id' => $userId])->first();
                $availableBalance = $balance->available_balance;
                $amount = BigNumber::new((string)$request->amount)->toString();

                if ((BigNumber::new($amount)->comp($availableBalance) > 0)) {
                    throw new ErrorException('balance.not_enough');
                }

                DB::transaction(function () use ($fromTable, $userId, $request, $amount, $availableBalance, $mainBalance) {
                    DB::table($fromTable)
                        ->lockForUpdate()
                        ->where([
                            'id' => $userId
                        ])->update([
                            'balance' => DB::raw($fromTable . '.balance - ' . $amount),
                            'available_balance' => DB::raw($fromTable . '.available_balance - ' . $amount),
                        ]);
                    $transfer = TransferHistory::create([
                        'user_id' => $userId,
                        'email' => Auth::user()['email'],
                        'coin' => $request->asset,
                        'source' => $mainBalance,
                        'destination' => Consts::TYPE_FUTURE_BALANCE,
                        'amount' => $amount,
                        'before_balance' => $availableBalance,
                        'after_balance' => BigNumber::new($availableBalance)->sub($amount)->toString()
                    ]);
                    $data = [
                        'userId' => $userId,
                        'amount' => BigNumber::new($request->amount)->toString(),
                        'asset' => $request->asset
                    ];
                    $topic = Consts::TOPIC_PRODUCER_TRANSFER;
                    Utils::kafkaProducer($topic, $data);

                    if ($mainBalance == Consts::TYPE_EXCHANGE_BALANCE) {
                        $transactionService = app(\App\Http\Services\TransactionService::class);
                        $transactionService->sendMETransferSpot($transfer, $data['userId'], $request->asset, BigNumber::new($data['amount'])->toString(), false);
                    }

//                    $resAccess = Http::withHeaders(['Authorization' => $token])->post($futureAccessTokenUrl, [
//                        'token' => trim(str_replace('Bearer', '', $token))
//                    ]);
//                    if ($resAccess->failed()) {
//                        throw new ErrorException($resAccess->json()['info']['message']);
//                    }
//                    $resDeposit = Http::withHeaders(['Authorization' => $token])->put($futureDepositUrl, [
//                        'amount' => $request->amount,
//                        'asset' => $request->asset
//                    ]);
//                    if ($resDeposit->failed()) {
//                        throw new ErrorException($resDeposit->json()['info']['message']);
//                    }
                });


                return [
                    'status' => true,
                    'msg' => 'transfer.success'
                ];
            } catch (Exception $e) {
                return [
                    'status' => false,
                    'msg' => $e->getMessage()
                ];
            }
        }
    }

    public function updateBalanceReferral($request)
    {
        $token = request()->header('authorization');
        $futureBaseUrl = env('FUTURE_API_URL');
        $futureDepositUrl = $futureBaseUrl . '/api/v1/account/deposit';
        try {
            DB::transaction(function () use ($request, $token, $futureDepositUrl) {
                $resDeposit = Http::withHeaders(['Authorization' => $token])->put($futureDepositUrl, [
                    'amount' => $request->amount,
                    'asset' => $request->asset
                ]);
                if ($resDeposit->failed()) {
                    throw new ErrorException($resDeposit->json()['info']['message']);
                }
            });
        } catch (Exception $e) {
            return [
                'status' => false,
                'msg' => $e->getMessage()
            ];
        }
    }

    public function transferFuture(array $request)
    {
        if ($request['from'] === Consts::TYPE_FUTURE_BALANCE && $request['to'] === Consts::TYPE_MAIN_BALANCE) {
            $mainBalance = $this->getMainBalanceTranfer();
            $fromTable = $this->getTableType($mainBalance, $request['asset']);
            try {
                $userId = $request['userId'];
                $user = User::findOrFail($userId);
                $balance = DB::raw($fromTable . '.balance + ' . $request['amount']);
                $availableBalance = DB::raw($fromTable . '.available_balance + ' . $request['amount']);
                $availableBalanceUser = DB::table($fromTable)->where(['id' => $userId])->value('available_balance');
                DB::transaction(function () use (
                    $fromTable,
                    $user,
                    $request,
                    $balance,
                    $availableBalance,
                    $availableBalanceUser,
                    $mainBalance
                ) {
                    DB::table($fromTable)->where([
                        'id' => $user->id
                    ])->update([
                        'balance' => $balance,
                        'available_balance' => $availableBalance,
                    ]);
                    $transfer = TransferHistory::create([
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'coin' => $request['asset'],
                        'source' => Consts::TYPE_FUTURE_BALANCE,
                        'destination' => $mainBalance,
                        'amount' => $request['amount'],
                        'before_balance' => $availableBalanceUser,
                        'after_balance' => BigNumber::new($availableBalanceUser)->add($request['amount'])->toString()
                    ]);
                    if ($mainBalance == Consts::TYPE_EXCHANGE_BALANCE) {
                        $transactionService = app(\App\Http\Services\TransactionService::class);
                        $transactionService->sendMETransferSpot($transfer, $user->id, $request['asset'], $request['amount'], true);
                    }
                });

                event(new MainBalanceUpdated($user->id, [
                    'total_balance' => $balance,
                    'available_balance' => $availableBalance,
                    'coin_used' => $request['asset']
                ]));
                return [
                    'status' => true,
                    'msg' => 'transfer.success'
                ];
            } catch (Exception $e) {
                return [
                    'status' => false,
                    'msg' => $e->getMessage()
                ];
            }
        }
        return [
            'status' => false,
            'msg' => 'not support'
        ];
    }

    private function getTableType($balanceType, $coinType)
    {
        if ($balanceType === Consts::TYPE_EXCHANGE_BALANCE) {
            return 'spot_' . $coinType . '_accounts';
        }

        return $coinType . '_accounts';
    }

    public function getTransferHistory(array $params, User $user): LengthAwarePaginator
    {
        return TransferHistory::query()
            ->select(
                "id",
                "amount",
                "coin",
                "email",
                "user_id",
                "created_at",
                "updated_at",
                DB::raw("case when transfer_history.source = 'future' and transfer_history.coin in ('usdt', 'usd') then 'usd_m'
                    when transfer_history.source = 'future' and transfer_history.coin not in ('usdt', 'usd') then 'coin_m'
                else transfer_history.source end as source"),
                DB::raw("case when transfer_history.destination = 'future' and transfer_history.coin in ('usdt', 'usd') then 'usd_m'
                    when transfer_history.destination = 'future' and transfer_history.coin not in ('usdt', 'usd') then 'coin_m'
                else transfer_history.destination end as destination")
            )
            ->where('user_id', $user->id)
            ->when(array_key_exists('coin', $params), function ($query) use ($params) {
                return $query->where('coin', $params['coin']);
            })->when(array_key_exists('dateStart', $params), function ($query) use ($params) {
                $dateEnd = !empty($params['dateEnd']) ? Carbon::create($params['dateEnd'] . " 23:59:59") : Carbon::now();
                $dateStart = Carbon::create($params['dateStart']);

                return $query->whereBetween('created_at', [$dateStart, $dateEnd]);
            })->when(array_key_exists('sort', $params), function ($query) use ($params) {
                if (!empty($params['sort'])) {
                    $query->orderBy($params["sort"], $params["sort_type"]);
                }
            })->orderBy('created_at', 'desc')
            ->paginate(Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE));
    }
}
