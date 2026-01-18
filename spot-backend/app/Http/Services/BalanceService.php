<?php
/**
 * Created by PhpStorm.
 * Date: 5/31/19
 * Time: 5:42 PM
 */

namespace App\Http\Services;

use App\Consts;
use App\Models\CoinsConfirmation;
use App\Utils\BigNumber;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class BalanceService
{
    private $priceService;

    public function __construct(PriceService $priceService)
    {
        $this->priceService = $priceService;
    }

    public function requestToFutureGetBalance($userId)
    {
        $futureBaseUrl = env('FUTURE_API_URL');
        $path = $futureBaseUrl . '/api/v1/balance/total-balances/' . $userId;
        $client = new Client();
        $resAccess = $client->request('GET', $path, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'futureUser' => env('FUTURE_USER'),
                'futurePassword' => env('FUTURE_PASSWORD')
            ]
        ]);

        if ($resAccess->getStatusCode() >= 400) {
            throw new \ErrorException(json_encode($resAccess->getBody()));
        }

        return json_decode($resAccess->getBody()->getContents());
    }

    public function getUserAccountsV2($userId): array
    {
        $currencies = MasterdataService::getCurrenciesAndCoins();
        $result = [];
        $listBalanceFuture = $this->requestToFutureGetBalance($userId) ?? null;
        $listBalanceFuture = $listBalanceFuture->data;

        foreach ($currencies as $currency) {
            $result[$currency]['coin'] = $currency;
            $tableSpot = "spot_{$currency}_accounts";
            $tableBalance = "{$currency}_accounts";
            $totalSpotBalance = DB::table($tableSpot)->where('id', $userId)->value('balance') ?? 0;
            $totalBalance = DB::table($tableBalance)->where('id', $userId)->first() ?? null;
            $result[$currency]['spot_total_balance'] = (new BigNumber($totalSpotBalance))->toString();
            $result[$currency]['future_total_balance'] = (new BigNumber(collect($listBalanceFuture)->first(function ($item) use (
                $currency
            ) {
                return strtoupper($currency) == $item->asset;
            })?->balance ?? 0))->toString();
            if ($currency != Consts::CURRENCY_USD) {
                $result[$currency]['blockchain_address'] = $totalBalance->blockchain_address ?? null;
            }
            if (Consts::CURRENCY_XRP == $currency || Consts::CURRENCY_TRX == $currency) {
                $result[$currency]['blockchain_address'] = (!empty($result[$currency]['blockchain_address']))
                        ? $result[$currency]['blockchain_address'] . "|" . $totalBalance->blockchain_sub_address
                        : null;
            }
            $result[$currency]['main_balance'] = (new BigNumber($totalBalance->balance ?? 0))->toString();
            $result[$currency]['total_balance'] = (new BigNumber($result[$currency]['main_balance']))
                ->add($result[$currency]['future_total_balance'])
                ->add($result[$currency]['spot_total_balance'])->toString();
        }

        return $result;
    }

    public function getUserAccounts($userId): array
    {
        $currencies = MasterdataService::getCurrenciesAndCoins();
        $result = [];

        foreach ($currencies as $currency) {
            // TODO: Join with MAM table
            $query = DB::table($currency . '_accounts');

            if ($currency === Consts::CURRENCY_BTC) {
                $query->leftJoin("margin_accounts", "margin_accounts.owner_id", $currency . '_accounts.id');
            }

            if ($currency === Consts::CURRENCY_AMAL) {
                $query->leftJoin("amal_margin_accounts", "amal_margin_accounts.owner_id", $currency . '_accounts.id');
            }

            $query->leftJoin("spot_{$currency}_accounts", "spot_{$currency}_accounts.id", $currency . '_accounts.id');

            $selects = [
                "{$currency}_accounts.balance",
                "{$currency}_accounts.available_balance",
                "spot_{$currency}_accounts.balance as spot_balance",
                "spot_{$currency}_accounts.available_balance as spot_available_balance",
            ];

            if (in_array($currency, Consts::AIRDROP_TABLES)) {
                $query->leftJoin("airdrop_{$currency}_accounts", "airdrop_{$currency}_accounts.id", $currency . '_accounts.id');
                array_push($selects, "airdrop_{$currency}_accounts.balance as airdrop_balance", "airdrop_{$currency}_accounts.balance_bonus as perpetual_airdrop_balance");
                array_push($selects, "airdrop_{$currency}_accounts.available_balance as available_airdrop_balance", "airdrop_{$currency}_accounts.available_balance_bonus as available_perpetual_airdrop_balance");
            }

            if ($currency === Consts::CURRENCY_BTC) {
                $selects[] = "margin_accounts.balance as margin_balance";
                $selects[] = "margin_accounts.available_balance as margin_available_balance";
            }

            if ($currency === Consts::CURRENCY_AMAL) {
                $selects[] = "amal_margin_accounts.balance as margin_balance";
                $selects[] = "amal_margin_accounts.available_balance as margin_available_balance";
            }

            if ($currency != Consts::CURRENCY_USD) {
                $selects[] = $currency . '_accounts.blockchain_address';
                $selects[] = $currency . '_accounts.usd_amount';
            }

            if (Consts::CURRENCY_XRP == $currency || Consts::CURRENCY_EOS == $currency) {
                $selects[] = $currency . '_accounts.blockchain_sub_address';
            }

            $query->addSelect($selects);
            $result[$currency] = $query->where($currency . '_accounts.id', $userId)->first();
        }

        $mapResult = [];

        foreach ($result as $key => $currency) {
            if (!$currency) {
                $currency = new \stdClass();
            }
            $mainBalance = @$currency->balance ?? 0;
            $marginBalance = @$currency->margin_balance ?? 0;
            $spotBalance = @$currency->spot_balance ?? 0;
            $airdropBalance = @$currency->airdrop_balance ?? 0;
            $airdropPerpetualBalance = @$currency->perpetual_airdrop_balance ?? 0;

            $currency->balance = (new BigNumber($mainBalance))->add($marginBalance)->add($spotBalance)->toString();
            if (in_array($key, Consts::AIRDROP_TABLES)) {
                $currency->balance = (new BigNumber($currency->balance))->add($airdropBalance)->add($airdropPerpetualBalance)->toString();
            }
            $mapResult[$key] = $currency;
        }

        return $mapResult;
    }

    public function getTotalBalances(): array
    {
        $result = [];

        foreach (MasterdataService::getCurrenciesAndCoins() as $currency) {
            $result[$currency] = $this->getTotalBalance($currency);
        }
        return $result;
    }

    public function getTotalBalance($currency): \Illuminate\Support\Collection
    {
        if ($currency === Consts::CURRENCY_BTC) {
            $queryRaw = "(SUM({$currency}_accounts.balance)
                + SUM(margin_accounts.balance)
                + SUM(spot_{$currency}_accounts.balance)) as balances,
                 (SUM({$currency}_accounts.available_balance)
                + SUM(margin_accounts.available_balance)
                + SUM(spot_{$currency}_accounts.available_balance)) as available_balances";

            return DB::table($currency . '_accounts')
                ->join("margin_accounts", "margin_accounts.id", $currency . '_accounts.id')
                ->join("spot_{$currency}_accounts", "spot_{$currency}_accounts.id", $currency . '_accounts.id')
                ->selectRaw($queryRaw)
                ->get();
        } else {
            $queryRaw = "(SUM({$currency}_accounts.balance)
                + SUM(spot_{$currency}_accounts.balance)) as balances,
                 (SUM({$currency}_accounts.available_balance)
                + SUM(spot_{$currency}_accounts.available_balance)) as available_balances";

            return DB::table($currency . '_accounts')
                ->join("spot_{$currency}_accounts", "spot_{$currency}_accounts.id", $currency . '_accounts.id')
                ->selectRaw($queryRaw)
                ->get();
        }
    }

    public function statisticBalance($currency)
    {
        /*if ($currency === Consts::CURRENCY_BTC) {
            $selectRaw = "(SUM({$currency}_accounts.balance)
                + SUM(margin_accounts.balance)
                + SUM(spot_{$currency}_accounts.balance)) as balances";

            return $account = DB::table($currency . '_accounts')
                ->join("margin_accounts", "margin_accounts.id", $currency . '_accounts.id')
                ->join("spot_{$currency}_accounts", "spot_{$currency}_accounts.id", $currency . '_accounts.id')
                ->selectRaw($selectRaw)
                ->value('balances');
        } else {*/
            $selectRaw = "(SUM({$currency}_accounts.balance)
                + SUM(spot_{$currency}_accounts.balance)) as balances";

            return $account = DB::table($currency . '_accounts')
                ->join("spot_{$currency}_accounts", "spot_{$currency}_accounts.id", $currency . '_accounts.id')
                ->selectRaw($selectRaw)
                ->value('balances');
        //}
    }

    public function statisticAvailableBalances(): array
    {
        $result = [];

        foreach (MasterdataService::getCurrenciesAndCoins() as $currency) {
            $result[$currency] = $this->statisticAvailableBalance($currency);
        }

        return $result;
    }

    public function statisticAvailableBalance($currency)
    {
        if ($currency === Consts::CURRENCY_BTC) {
            $selectRaw = "(SUM({$currency}_accounts.available_balance)
                + SUM(margin_accounts.available_balance)
                + SUM(spot_{$currency}_accounts.available_balance)) as available_balances";

            return DB::table($currency . '_accounts')
                ->join("margin_accounts", "margin_accounts.id", $currency . '_accounts.id')
                ->join("spot_{$currency}_accounts", "spot_{$currency}_accounts.id", $currency . '_accounts.id')
                ->selectRaw($selectRaw)
                ->value('balances');
        } else {
            $selectRaw = "(SUM({$currency}_accounts.available_balance)
                + SUM(spot_{$currency}_accounts.available_balance)) as available_balances";

            return DB::table($currency . '_accounts')
                ->join("spot_{$currency}_accounts", "spot_{$currency}_accounts.id", $currency . '_accounts.id')
                ->selectRaw($selectRaw)
                ->value('balances');
        }
    }

    public function partnerAdminGetFutureBalance($adminToken, $userIds) {
        $futureBaseUrl = env('FUTURE_API_URL');
        $path = $futureBaseUrl . '/api/v1/balance/admin-get-list-user-balances';
        
        //test
        // $adminToken = '264|ll3JQLoaKbEkMOwtWKsgtSCDq0RPwQn7Mz97uOAk9a3a0625';
        // $userIds = [537, 538, 539, 540, 541, 542];

        $response = Http::withToken($adminToken)
            ->post($path, ['userIds' => $userIds]);
        
        return $response->json();
    }

    public function partnerAdminGetFutureBalanceAuth($userIds) {
        $futureBaseUrl = env('FUTURE_API_URL');
        $futureUser = env("FUTURE_USER");
        $futurePassword = env("FUTURE_PASSWORD");

        $path = $futureBaseUrl . '/api/v1/balance/admin-get-list-user-balances';
        // $userIds = [699];
        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->withBody(http_build_query([
            'userIds' => $userIds,
            'futureUser' => $futureUser,
            'futurePassword' => $futurePassword
        ]), 'application/x-www-form-urlencoded')->post($path);
        
        return $response->json();
        
    }

    public function getTableWithType($balanceType, $coinType): string
    {
        // Exchange Table
        if ($balanceType == Consts::TYPE_EXCHANGE_BALANCE) {
            return 'spot_' . $coinType . '_accounts';
        }

        // Main Table
        return $coinType . '_accounts';
    }

    public function getFutureBalance($token, $userIds) {
        $res = $this->partnerAdminGetFutureBalance($token, $userIds);
        return $res['code'] == 200 ? $res['data'] : [];
    }

    public function listBalanceIds(array $accountIds, $request)
    {
        $futureBalance = collect($this->partnerAdminGetFutureBalanceAuth($accountIds))->get('data', []);
        $currencies = CoinsConfirmation::query()
            ->select('coin')
            ->pluck('coin');

        $balanceType = Consts::TYPE_EXCHANGE_BALANCE;
        $accounts = [];
        $listSpotPriceVsUsdt = [];

        foreach ($currencies as $currency) {
            if (empty($listSpotPriceVsUsdt[$currency])) {
                if (in_array($currency, ['usdt', 'usd'])) {
                    $listSpotPriceVsUsdt[$currency] = 1;
                } else {
                    $listSpotPriceVsUsdt[$currency] = $this->priceService->getCurrentPrice('usdt', $currency)->price ?? 0;
                }
            }

            $currencyTable = $this->getTableWithType($balanceType, $currency);

            $balances = DB::connection('master')
                ->table($currencyTable, 'a')
                ->whereIn('a.id', $accountIds)
                ->get();


            foreach ($balances as $item) {
                //feature
                $futureDataById = collect($futureBalance[$item->id] ?? []);
                $detailData = collect($futureDataById->get('detail', []));

                $key = $detailData->keys()->first(function ($key) use ($currency) {
                    return strtolower($key) === strtolower($currency);
                });

                if ($key !== null) {
                    $processedData = [
                        'totalAmountUSDT' => $futureDataById->get('usdtBalance'),
                        'list' => $detailData->map(function ($balance, $asset) {
                            return [
                                'asset' => $asset,
                                'balance' => $balance
                            ];
                        })->values()->all()
                    ];

                    $accounts[$item->id]['feature'] = $processedData;
                }
                // #Feature

                $accounts[$item->id]['spot']['list'][] = [
                    'asset' => $currency,
                    'balance' => $item->balance
                ];

                $balanceUsdt = BigNumber::new(safeBigNumberInput($item->balance))->mul(safeBigNumberInput($listSpotPriceVsUsdt[$currency]));
                if (empty($accounts[$item->id]['spot']['totalAmountUSDT'])) {
                    $accounts[$item->id]['spot']['totalAmountUSDT'] = 0;
                }
                $accounts[$item->id]['spot']['totalAmountUSDT'] = BigNumber::new($accounts[$item->id]['spot']['totalAmountUSDT'])->add($balanceUsdt)->toString();
            }
        }

        return [
            "accounts" => $accounts,
            "listSpotPriceVsUsdt" => $listSpotPriceVsUsdt,
        ];
    }
}
