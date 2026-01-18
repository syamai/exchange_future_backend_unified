<?php

/**
 * Created by PhpStorm.
 * Date: 5/14/19
 * Time: 4:32 PM
 */

namespace App\Http\Services;

use App\Consts;
use App\Jobs\SendBalance;
use App\Models\Coin;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class DepositService
 * @package App\Http\Services
 */
class DepositService
{

    /**
     * @var array
     */
    private $accounts = [];
    /**
     * @param $currency
     * @return array
     * @throws \Exception
     */
    public function create($currency, $networkId)
    {
        $userId = auth()->id();
        $currency = $this->updateCurrencyIfNeed($currency);
        $existedUserAddress = $this->getUserAddress($currency, $userId, $networkId);

        if ($existedUserAddress) {
            //$this->updateIfEth($currency, $existedUserAddress->blockchain_address, $userId);
            //$this->updateIfBnb($currency, $existedUserAddress->blockchain_address, $userId);
            return $this->hadAddress($currency, $existedUserAddress->blockchain_address);
        }

        $address = $this->getAvailableDepositAddress($currency, $networkId);

        if ($address) {
            return $this->createUserAccount($address, $currency, $userId, $networkId);
        }

        throw new HttpException(422, __('exception.empty_address'));
    }

    private function updateIfEth($currency, $blockchain_address, $userId)
    {
        if ($currency === Consts::CURRENCY_ETH) {
            $this->addEthAccount();
            $this->updateAccounts($userId, compact('blockchain_address'));
        }
    }

    private function updateIfBnb($currency, $blockchain_address, $userId)
    {
        if ($currency === Consts::CURRENCY_BNB) {
            $this->addBnbAccount();
            $this->updateAccounts($userId, compact('blockchain_address'));
        }
    }


    /**
     * @param $currency
     * @param $blockchainAddress
     * @return array
     */
    private function hadAddress($currency, $blockchainAddress)
    {
        /*if (Consts::CURRENCY_XRP === $currency || Consts::CURRENCY_EOS === $currency || Consts::CURRENCY_TRX === $currency) {
            return $this->createWithTagAddress($blockchainAddress);
        }*/
        return ['blockchain_address' => $blockchainAddress];
    }

    /**
     * @param $address
     * @param $currency
     * @param $userId
     * @return array
     */
    private function createUserAccount($address, $currency, $userId, $networkId)
    {
        $blockchainAddress = $address->blockchain_address;
        $updateData = ['blockchain_address' => $blockchainAddress];
        $userBlockchainTag = null;

        /*if (Consts::CURRENCY_XRP === $currency || Consts::CURRENCY_EOS === $currency || Consts::CURRENCY_TRX === $currency) {
            $userBlockchainTag = mt_rand(100000, 999999);
            $updateData['blockchain_sub_address'] = $userBlockchainTag;
        }*/

        //$this->updateAccounts($userId, $updateData);
        $this->addUserBlockchainAddress($userId, $currency, $blockchainAddress, $networkId, $userBlockchainTag);

        $this->destroyAddress($address->id);

        SendBalance::dispatchIfNeed($userId, [$currency], Consts::TYPE_MAIN_BALANCE);
        return $updateData;
    }

    public function createByUserId($currency, $userId)
    {
        $currency = $this->updateCurrencyIfNeed($currency);
        $existedUserAddress = $this->getUserAddress($currency, $userId);

        if ($existedUserAddress) {
            return $this->hadAddress($currency, $existedUserAddress->blockchain_address);
        }
        $address = $this->getAvailableDepositAddress($currency);

        if ($address) {
            return $this->createUserAccount($address, $currency, $userId);
        } else {
            // throw new HttpException(422,__('exception.empty_address'));
        }
    }

    /**
     * @param $userId
     * @param $data
     */
    private function updateAccounts($userId, $data)
    {
        foreach ($this->accounts as $account) {
            $this->updateAccount($userId, $account, $data);
        }
    }

    /**
     * @param $userId
     * @param $table
     * @param $data
     */
    private function updateAccount($userId, $table, $data)
    {
        DB::connection('master')->table($table)
            ->where('id', $userId)
            ->update($data);
    }

    /**
     * @param $currency
     * @param $userId
     * @return mixed
     */
    private function getUserAddress($currency, $userId, $networkId)
    {
        return DB::connection('master')->table('user_blockchain_addresses')
            ->where('currency', $currency)
            ->where('user_id', $userId)
            ->where('network_id', $networkId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param $currency
     * @return mixed
     */
    private function getAvailableDepositAddress($currency, $networkId)
    {
        return DB::connection('master')->table('blockchain_addresses')
            ->where('currency', $currency)
            ->where('available', true)
            ->where('network_id', $networkId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param $addressId
     * @return mixed
     */
    private function destroyAddress($addressId)
    {
        return DB::connection('master')->table('blockchain_addresses')
            ->where('id', $addressId)
            ->delete();
    }

    /**
     * @param $userId
     * @param $currency
     * @param $blockchainAddress
     * @param null $tag
     */
    private function addUserBlockchainAddress($userId, $currency, $blockchainAddress, $networkId, $tag = null)
    {
        if ($tag) {
            $blockchainAddress .= Consts::XRP_TAG_SEPARATOR . $tag;
        }

        DB::connection('master')->table('user_blockchain_addresses')
            ->insert([
                'user_id' => $userId,
                'currency' => $currency,
                'network_id' => $networkId,
                'blockchain_address' => $blockchainAddress,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
    }

    /**
     * @param $currency
     * @return string
     */
    private function updateCurrencyIfNeed($currency)
    {
        /*$type = Coin::network()
            ->where('coin', $currency)
            ->value('type');

        if ($type === Consts::ETH_TOKEN || $type === Consts::CURRENCY_ETH) {
            $this->addEthAccount();
            return Consts::CURRENCY_ETH;
        }

        if ($type === Consts::BNB_TOKEN || $type === Consts::CURRENCY_BNB) {
            $this->addBnbAccount();
            return Consts::CURRENCY_BNB;
        }*/

        array_push($this->accounts, "{$currency}_accounts");
        return $currency;
    }

    private function addEthAccount()
    {
        $allCoins = Coin::network()
            ->select('coin', 'type')
            ->get();

        foreach ($allCoins as $coin) {
            if ($coin->type === Consts::ETH_TOKEN || $coin->type === Consts::CURRENCY_ETH) {
                array_push($this->accounts, "{$coin->coin}_accounts");
            }
        }
    }

    private function addBnbAccount()
    {
        $allCoins = Coin::network()
            ->select('coin', 'type')
            ->get();

        foreach ($allCoins as $coin) {
            if ($coin->type === Consts::BNB_TOKEN || $coin->type === Consts::CURRENCY_BNB) {
                array_push($this->accounts, "{$coin->coin}_accounts");
            }
        }
    }

    /**
     * @param $data
     * @return array
     */
    private function createWithTagAddress($data)
    {
        $data = explode(Consts::XRP_TAG_SEPARATOR, $data);
        return [
            'blockchain_sub_address' => $data[1],
            'blockchain_address' => $data[0]
        ];
    }
}
