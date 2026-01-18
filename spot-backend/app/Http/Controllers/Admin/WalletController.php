<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Facades\FormatFa;
use App\Http\Controllers\AppBaseController;
use App\Http\Requests\CreateAdminWalletAPIRequest;
use App\Http\Services\Blockchain\SotatekBlockchainService;
use App\Http\Services\HotWalletService;
use App\Http\Services\UserService;
use App\Models\AdminWallet;
use App\Models\Coin;
use App\Models\User;
use Illuminate\Http\Request;
use App\Utils\BigNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Services\Blockchain\CoinConfigs;
use Illuminate\Http\JsonResponse;

class WalletController extends AppBaseController
{
    protected HotWalletService $hotWalletService;

    public function __construct(HotWalletService $hotWalletService)
    {
        $this->hotWalletService = $hotWalletService;
    }

    public function create(CreateAdminWalletAPIRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $input = $request->all();
            $wallet = AdminWallet::create($input);
            DB::commit();
            return $this->sendResponse($wallet);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function removeWallet($id)
    {
        DB::beginTransaction();
        try {
            AdminWallet::destroy($id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getWalletInfo(Request $request, $currency): JsonResponse
    {
        $forceUpdate = $request->forceUpdate;

        $walletDataKey = "admin_wallet_$currency";
        $data = Cache::get($walletDataKey);
        if (!$forceUpdate && $data) {
            return $this->sendResponse($data);
        }

        $data = [];

        $adminWalletTable = $forceUpdate ? AdminWallet::on('master') : AdminWallet::on();
        $coldWallets = $adminWalletTable->where('currency', $currency)
            ->orderBy('id')
            ->get()
            ->all();

        foreach ($coldWallets as $wallet) {
            try {
                $wallet->balance = $this->queryColdWalletBalance($wallet);
            } catch (\Exception $e) {
                Log::error($e);
            }
        }

        $blockchainService = new SotatekBlockchainService($currency);
        $hotWallet = $blockchainService->getWalletInfo();
        $hotWallet['type'] = Consts::WALLET_TYPE_HOT;
        $data['wallets'] = array_merge([(object)$hotWallet], $coldWallets);

        $totalBalance = BigNumber::new(0);
        foreach ($data['wallets'] as $wallet) {
            $totalBalance = $totalBalance->add($wallet->balance);
        }

        $data['total_balance'] = $totalBalance->toString();
        //TODO: MUST fix: calculate total user balance
        $maxBotId = User::where('type', Consts::USER_TYPE_BOT)->max('id');
        if (!$maxBotId) {
            $maxBotId = 0;
        }
        $totalMainBalance = DB::table($currency . '_accounts')
            ->where('id', '>', $maxBotId)
            ->where('balance', '>', 0)
            ->sum('balance');
        $totalSpotBalance = DB::table('spot_' . $currency . '_accounts')
            ->where('id', '>', $maxBotId)
            ->where('balance', '>', 0)
            ->sum('balance');
        $totalMarginBalance = 0;
        if ($currency === Consts::CURRENCY_BTC) {
            $totalMarginBalance = DB::table('margin_accounts')
                ->where('id', '>', $maxBotId)
                ->where('balance', '>', 0)
                ->sum('balance');
        }
        $data['total_user_balance'] = BigNumber::new($totalMainBalance)
            ->add($totalSpotBalance)
            ->add($totalMarginBalance)
            ->toString();

        Cache::set($walletDataKey, $data, 5);
        return $this->sendResponse($data);
    }

    private function queryColdWalletBalance($wallet)
    {
        $url = config('blockchain.sb_url') . '/api/v2/public/' . $wallet->currency . '/balance/' . $wallet->blockchain_address;
        $client = new \GuzzleHttp\Client(['verify' => false]);
        $response = $client->request('GET', $url, []);
        $data = json_decode($response->getBody()->getContents());
        return $data->balanceString;
    }

    public function getTotalBalances()
    {
        try {
            $data = app(UserService::class)->getTotalBalances();
            return $this->sendResponse($data);
        } catch (\Exception $exception) {
            Log::error($exception);
            $this->sendError($exception->getMessage());
        }
    }
    public function getWalletBalances()
    {

        $dataCoins = $this->hotWalletService->statisticBalance();

        $coins = Coin::network()->pluck('coin')->toArray();
        $totalBalance = $this->getTotalBalances();
        $totalBalance = (json_decode($totalBalance->getContent(), true)['data']);
        $dataWallet = [];

        try {
            foreach ($coins as $coin) {
                try {
                    $dataErc20 = [];
                    $coinNetwork = FormatFa::formatCoinNetwork($coin);
                    if (!$coinNetwork) {
                        continue;
                    }

                    $coinConfig = CoinConfigs::getCoinConfig($coinNetwork->coin, $coinNetwork->network_id);
                    if (!$coinConfig) {
                        continue;
                    }
                    $currency = $coinConfig->getCurrentTx();
                    $blockchainService = new SotatekBlockchainService($coinNetwork);

                    if ($coinConfig->isBEP20Token()) {
                        $blockchainServiceBep20 = new SotatekBlockchainService('bnb');
                        $feeCollectColdWallet = $blockchainServiceBep20->fixTransactionAmount($dataCoins[$currency]['feeCollectColdWallet'], true);
                        $feeCollectHotWallet = $blockchainServiceBep20->fixTransactionAmount($dataCoins[$currency]['feeCollectHotWallet'], true);
                    }

                    if ($coinConfig->isEthErc20Token()) {
                        $blockchainServiceErc20 = new SotatekBlockchainService('eth');
                        $feeCollectColdWallet = $blockchainServiceErc20->fixTransactionAmount($dataCoins[$currency]['feeCollectColdWallet'], true);
                        $feeCollectHotWallet = $blockchainServiceErc20->fixTransactionAmount($dataCoins[$currency]['feeCollectHotWallet'], true);
                    }

                    if (!$coinConfig->isBEP20Token() && !$coinConfig->isEthErc20Token()) {
                        $feeCollectColdWallet = $blockchainService->fixTransactionAmount($dataCoins[$currency]['feeCollectColdWallet'], true);
                        $feeCollectHotWallet = $blockchainService->fixTransactionAmount($dataCoins[$currency]['feeCollectHotWallet'], true);
                    }

                    $dataErc20[$currency] = [
                        'hotWalletAddress' => $dataCoins[$currency]['hotWalletAddress'],
                        'totalBalance' => $blockchainService->fixTransactionAmount($dataCoins[$currency]['totalBalance'], true),
                        'hotWalletBalance' => $blockchainService->fixTransactionAmount($dataCoins[$currency]['hotWalletBalance'], true),
                        'amountCollectColdWallet' => $blockchainService->fixTransactionAmount($dataCoins[$currency]['amountCollectColdWallet'], true),
                        'feeCollectColdWallet' => $feeCollectColdWallet ?? 0,
                        'feeCollectHotWallet' => $feeCollectHotWallet ?? 0,
                        'balances' => $totalBalance[$coin]['0']['balances'],
                        'available_balances' => $totalBalance[$coin]['0']['available_balances'],
                        'token_type' => $coinConfig->getTokenType(),
                    ];

                    $dataWallet[$coin] = $dataErc20[$currency];
                } catch (\Exception $e) {
                    Log::error($e);
                }
            }

            $dataWallet['USD'] = [
                'hotWalletAddress' => '',
                'totalBalance' => 0,
                'hotWalletBalance' => 0,
                'amountCollectColdWallet' => 0,
                'feeCollectColdWallet' => 0,
                'feeCollectHotWallet' => 0,
                'balances' => $totalBalance['usd']['0']['balances'],
                'available_balances' => $totalBalance['usd']['0']['available_balances'],
            ];

            $data = $dataWallet;

            return $this->sendResponse($data) ;
        } catch (\Exception $exception) {
            Log::error($exception);
            $this->sendError($exception->getMessage());

//        try {
//            $userService = new UserService;
//            $walletBallance = new WalletBalanceService($userService);
//            return $this->sendResponse($walletBallance->getWalletBalance());
//        }
//        catch(\Exception $ex) {
//            Log::error($ex);
//            $this->sendError($ex);
//
        }
    }
}
