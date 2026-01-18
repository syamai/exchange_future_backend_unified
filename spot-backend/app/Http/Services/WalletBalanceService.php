<?php

namespace App\Http\Services;

use App\Consts;
use App\Facades\FormatFa;
use App\Http\Services\UserService;
use App\Http\Services\Blockchain\SotatekBlockchainService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletBalanceService
{

    private $userSerivce;
    private $blockchainService;

    public function __construct(UserService $userSerivce)
    {
        $this->userSerivce = $userSerivce;
        $this->blockchainService = new SotatekBlockchainService(Consts::CURRENCY_BTC);
    }

    public function getWalletBalance()
    {
        try {
            $dataCurrencies = json_decode($this->blockchainService->getWalletBalance(), true);
            $coinsDB = DB::table('coin_settings')->pluck('coin')->unique()->toArray();
            $totalBalance = $this->userSerivce->getTotalBalances();

            foreach ($dataCurrencies as $item => $value) {
                $coinFormated = FormatFa::formatCoin($item);
                if ($item === Consts::UPDATED_CURRENCY_EOS) {
                    unset($dataCurrencies[$item]);
                }

                if (in_array($coinFormated, $coinsDB)) {
                    $dataCurrencies[$item] = $this->getParticularWalletBalance($value, $totalBalance[$coinFormated], $item);
                    if ($item !== $coinFormated) {
                        $dataCurrencies[$coinFormated] = $dataCurrencies[$item];
                        unset($dataCurrencies[$item]);
                    }
                } else {
                    unset($dataCurrencies[$item]);
                }
            }
        } catch (\Exception $ex) {
            Log::error($ex);
            throw $ex;
        }

        return $dataCurrencies;
    }

    public function getParticularWalletBalance($particularWallet, $particularBalance, $currency)
    {
        try {
            $blockchainService = new SotatekBlockchainService($currency);

            if ($currency === strtolower(Consts::CURRENCY_USD)) {
                $walletBalance = [
                    'hotWalletAddress' => '',
                    'totalBalance' => 0,
                    'hotWalletBalance' => 0,
                    'amountCollectColdWallet' => 0,
                    'feeCollectColdWallet' => 0,
                    'feeCollectHotWallet' => 0,
                    'balances' => $particularBalance ? $particularBalance->first()->{'balances'} : 0,
                    'available_balances' => $particularBalance ? $particularBalance->first()->{'available_balances'} : 0,
                ];
            } else {
                $walletBalance = [
                    'hotWalletAddress' => $particularWallet['hotWalletAddress'],
                    'totalBalance' => $blockchainService->fixTransactionAmount($particularWallet['totalBalance'], true),
                    'hotWalletBalance' => $blockchainService->fixTransactionAmount($particularWallet['hotWalletBalance'], true),
                    'amountCollectColdWallet' => $blockchainService->fixTransactionAmount($particularWallet['amountCollectColdWallet'], true),
                    'feeCollectColdWallet' => $blockchainService->fixTransactionAmount($particularWallet['feeCollectColdWallet'], true),
                    'feeCollectHotWallet' => $blockchainService->fixTransactionAmount($particularWallet['feeCollectHotWallet'], true),
                    'balances' => $particularBalance ? $particularBalance->first()->{'balances'} : 0,
                    'available_balances' => $particularBalance ? $particularBalance->first()->{'available_balances'} : 0,
                ];
            }
        } catch (\Exception $ex) {
            Log::error($ex);
        }

        return $walletBalance;
    }
}
