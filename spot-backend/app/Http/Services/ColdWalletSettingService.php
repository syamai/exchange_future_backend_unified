<?php

namespace App\Http\Services;

use App\Consts;
use App\Models\ColdWalletSetting;
use App\Models\CoinsConfirmation;
use App\Models\Settings;
use App\Http\Services\Blockchain\CoinConfigs;
use App\Http\Services\Blockchain\SotatekBlockchainService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\BlockchainAddress;
use App\Facades\CheckFa;
use App\Facades\FormatFa;

class ColdWalletSettingService
{
    private $model;

    public function __construct(ColdWalletSetting $model)
    {
        $this->model = $model;
    }

    public function getColdWalletSettingV2()
    {
        $blockchainService = new SotatekBlockchainService(Consts::CURRENCY_SOL);
        $coldWallet = json_decode($blockchainService->getColdWallet(), true);
        $coinsDB = DB::table('coins')->where('env', config('blockchain.network'))->pluck('coin')->toArray();
        logger()->info("RESPONSE COLD WALLET 1======" .json_encode($coldWallet));
        $result = [];
        foreach ($coldWallet as $item) {
            logger()->info("Item ======" .json_encode($coldWallet));
            $coinFormated  = FormatFa::formatCoin($item['currency']);
            if (!in_array($coinFormated, $coinsDB)) {
                continue;
            }
            $result[$coinFormated]['address'] = $item['address'];
            $result[$coinFormated]['coin'] = $coinFormated;
        }

        $holderEmail = DB::table('settings')
            ->where('key', '=', 'cold_wallet_holder_email')
            ->value('value') ?? null;


        return [
            'cold_wallet' => array_values($result),
            'cold_wallet_holder_email' => $holderEmail,
        ];
    }

    public function getColdWalletSetting()
    {
        $blockchainService = new SotatekBlockchainService(Consts::CURRENCY_BTC);
        $coldWallet = json_decode($blockchainService->getColdWallet(), true);
        $updatedColdWallets = array_shift($coldWallet);

        $coinsDB = DB::table('coins')->where('env', config('blockchain.network'))->pluck('coin')->toArray();

        $eosIndex = -1;
        foreach ($updatedColdWallets as $item => $value) {
            $coinFormated  = FormatFa::formatCoin($value['currency']);
            $coinParams = explode('.', $value['currency']);

            if ($coinParams[0] === Consts::ERC20_WEBHOOK) {
                $updatedColdWallets[$item] = $this->updateErc20Address($value, $updatedColdWallets);
            }

            if ($value['currency'] === Consts::CURRENCY_EOS) {
                $eosIndex = $item;
            }

            if ($value['currency'] === Consts::UPDATED_CURRENCY_EOS) {
                $updatedColdWallets[$item]['currency'] = Consts::CURRENCY_EOS;
                $updatedColdWallets[$item]['networkSymbol'] = Consts::CURRENCY_EOS;
                $updatedColdWallets[$item]['coin'] = Consts::CURRENCY_EOS;
            }

            if (in_array($coinFormated, $coinsDB)) {
                $updatedColdWallets[$item] = $this->getColdWalletSettingService($value['currency'], $updatedColdWallets[$item], $value, $coinFormated);
            } else {
                unset($updatedColdWallets[$item]);
            }
        }

        if ($eosIndex != -1) {
            unset($updatedColdWallets[$eosIndex]);
        }

        $holderEmail = DB::table('settings')
        ->select('id', 'key', 'value')
        ->where('key', '=', 'cold_wallet_holder_email')
        ->first();

        $holderName = DB::table('settings')
        ->select('id', 'key', 'value')
        ->where('key', '=', 'cold_wallet_holder_name')
        ->first();

        $holderMobileNo = DB::table('settings')
        ->select('id', 'key', 'value')
        ->where('key', '=', 'cold_wallet_holder_mobile_no')
        ->first();

        $dataReturn = [
            'cold_wallet' => array_values($updatedColdWallets),
            'cold_wallet_holder_email' => $holderEmail != null ? $holderEmail->value : null,
            'cold_wallet_holder_name' => $holderName != null ? $holderName->value : null,
            'cold_wallet_holder_mobile_no' => $holderMobileNo != null ? $holderMobileNo->value : null
        ];

        return $dataReturn;
    }

    public function updateColdWalletSetting($coldWalletSetting, $email)
    {
        DB::beginTransaction();

        try {
            foreach ($coldWalletSetting as $item => $value) {
                if (empty($value['address']) && empty($value['lowerThreshold']) && empty($value['upperThreshold'])) {
                    $this->resetColdWalletSettingService($value['currency']);
                } else {
                    $this->updateColdWalletSettingService($value['currency'], $value);
                }
            }

            Settings::updateOrCreate(
                ['key' => 'cold_wallet_holder_email'],
                ['key' => 'cold_wallet_holder_email', 'value' => $email]
            );
//            $blockchainService = new SotatekBlockchainService(Consts::CURRENCY_XRP);
            $blockchainService = new SotatekBlockchainService(Consts::CURRENCY_SOL);
            $body = [
                'mailerReceiver' => $email
            ];
            $blockchainService->updateMailColdWallet($body);

            DB::commit();
            return true;
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            throw $ex;
        }
    }

    public function updateColdWalletSettingService($currency, $value)
    {
        $blockchainService = new SotatekBlockchainService($currency);

        $body = [
            'upperThreshold' => $blockchainService->fixTransactionAmount($value['upperThreshold'], false),
            'lowerThreshold' => $blockchainService->fixTransactionAmount($value['lowerThreshold'], false),
            'address' => $value['address']
        ];

        $blockchainService->updateColdWallet($body);
    }

    public function resetColdWalletSettingService($currency)
    {
        $blockchainService = new SotatekBlockchainService($currency);
        $blockchainService->resetColdWallet();
    }

    public function getColdWalletSettingService($currency, $coldWalletValue, $value, $coin)
    {
        $blockchainService = new SotatekBlockchainService($currency);
        $coldWalletValue['upperThreshold'] = $value['upperThreshold'] ? $blockchainService->fixTransactionAmount($value['upperThreshold'], true) : null;
        $coldWalletValue['lowerThreshold'] = $value['lowerThreshold'] ? $blockchainService->fixTransactionAmount($value['lowerThreshold'], true) : null;

        $coldWalletValue['coin'] = $coin;

        return $coldWalletValue;
    }

    public function validateColdWalletAddress($value)
    {
        $eachAddress = BlockchainAddress::select('blockchain_address')
                                        ->where('currency', $value['currency'])
                                        ->pluck('blockchain_address')
                                        ->toArray();
        $userBlockchainAddress = DB::table('user_blockchain_addresses')
                                    ->select('blockchain_address')
                                    ->where('currency', $value['currency'])
                                    ->pluck('blockchain_address')
                                    ->toArray();

        foreach ($eachAddress as $address) {
            if (!isset($value['address'])) {
                return false;
            }
            if ($value['address'] === $address) {
                return false;
            }
        }

        foreach ($userBlockchainAddress as $address) {
            if (!isset($value['address'])) {
                return false;
            }
            if ($value['address'] === $address) {
                return false;
            }
        }
        return true;
    }

    public function validateColdWalletAddressFromExternal($value)
    {

        $coin = FormatFa::formatCoin($value['currency']);
        if (!$value['address']) {
            return CheckFa::blockchainAddress($coin, null);
        }
        return CheckFa::blockchainAddress($coin, $value['address']);
    }

    public function commonValidateAddress($value)
    {
        $validAddress = $this->validateColdWalletAddress($value);
        if (!$validAddress) {
            return false;
        } else {
            $internalAddress = $this->validateColdWalletAddressFromExternal($value);
            if (!$internalAddress) {
                return false;
            }
        }
        return true;
    }

    public function updateErc20Address($coin, $updatedColdWallets)
    {
        foreach ($updatedColdWallets as $item => $value) {
            if ($value['currency'] === Consts::CURRENCY_ETH && $value['address'] !== null) {
                $coin['address'] = $value['address'];
            }
        }
        return $coin;
    }
}
