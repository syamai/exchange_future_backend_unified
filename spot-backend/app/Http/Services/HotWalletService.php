<?php

namespace App\Http\Services;

use App\Http\Services\Blockchain\CoinConfigs;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class HotWalletService
{
    public function createEOSReceiveAddress($params): void
    {
        $currency = 'eos';
        $number = Arr::get($params, 'number');
        $address = Arr::get($params, 'address');
        for ($i = 0; $i < $number; $i++) {
            $id = DB::table('blockchain_addresses')->insertGetId(
                [
                    'currency' => $currency,
                    'device_id' => $currency,
                    'blockchain_address' => $address,
                    'address_id' => $currency,
                    'path' => $currency
                ]
            );
            DB::table('blockchain_addresses')
                ->where('id', $id)
                ->update([
                    'address_id' => $id,
                    'path' => $id,
                ]);
        }
    }

    private function getApiUri($currency, $networkId = null): string
    {
        $coinConfig = CoinConfigs::getCoinConfig($currency, $networkId);
        return $coinConfig->getDomainAPI();
    }

    public function createUSDTReceiveAddress($params)
    {
        $currency = 'usdt';
        $network = config('blockchain.network');
        $index = DB::table('blockchain_addresses')->where('currency', $currency)->count();
        $amount = (int)(Arr::get($params, 'amount'));
        $password = Arr::get($params, 'password');
        $masterPrivateKey = Arr::get($params, 'masterPrivateKey');


        $apiUri = $this->getApiUri($currency);
        $path = $apiUri . "/api/$currency/address";
        $apiKey = config('blockchain.x_api_key_wallet');

        $client = new Client();
        $response = $client->post($path, [
            'headers' => [
                "x-api-key" => $apiKey,
            ],
            'json' => [
                'pass' => $password,
                'amount' => $amount,
                'index' => $index,
                'masterprivatekey' => $masterPrivateKey,
                'network' => $network
            ],
            'timeout' => 20
        ]);
        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getBody(), true);
            $addresses = Arr::get($data, 'addresses');
            $index = Arr::get($data, 'index');
            $count = $index;
            foreach ($addresses as $address) {
                DB::table('blockchain_addresses')->insert(
                    [
                        'currency' => $currency,
                        'device_id' => $currency,
                        'blockchain_address' => $address,
                        'address_id' => $count,
                        'path' => $currency
                    ]
                );
                $count++;
            }
            return 'ok';
        }
    }

    public function approveTransaction($params)
    {
        $amount = (Arr::get($params, 'amount'));
        $toAddress = Arr::get($params, 'toAddress');
        $currency = Arr::get($params, 'currency');
        $networkId = Arr::get($params, 'network_id');
        $userId = Arr::get($params, 'userId');
        $accountId = Arr::get($params, 'accountId', "");
        $apiUri = $this->getApiUri($currency, $networkId);

        $client = new Client();

        $coin = CoinConfigs::getCoinConfig($currency, $networkId);
        $getCurrentThreshold = $coin->getCurrentThreshold();

        try {
            $path = $apiUri . "/api/{$getCurrentThreshold}/withdrawal/approve";
            $apiKey = config('blockchain.x_api_key_wallet');
            logger()->info('WithdrawJob URL ==============' . $path);
            logger()->info('WithdrawJob INFO ======' . json_encode(['toAddress' => $toAddress,'amount' => $amount]));

            $response = $client->post($path, [
                'headers' => [
                    "x-api-key" => $apiKey,
                ],
                'json' => [
                    'toAddress' => $toAddress,
                    'amount' => $amount,
                    'userId' => $userId,
                    'accountId' => $accountId,
                    'walletType' => 'SPOT',
                ],
                'timeout' => 20
            ]);

            logger()->info('WithdrawJob HTTP Code ==============' . $response->getStatusCode());
            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);
                return Arr::get($data, 'id');
            }
        } catch (\Exception $e) {
            logger()->error("REQUEST TO WALLET FAIL ======== " . $e->getMessage());
            return 0;
        }
    }

    public function signTransaction($params)
    {
        $amount = (int)(Arr::get($params, 'amount'));
        $toAddress = Arr::get($params, 'toAddress');
        $currency = Arr::get($params, 'currency');

        $apiUri = $this->getApiUri($currency);
        $apiKey = config('blockchain.x_api_key_wallet');

        $client = new Client();

        $path = $apiUri . "/api/$currency/withdrawal/accept";

        $response = $client->post($path, [
            'headers' => [
                "x-api-key" => $apiKey,
            ],
            'json' => [
                'pass' => $toAddress,
                'withdrawalId' => $amount,
            ],
            'timeout' => 20
        ]);

        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getBody(), true);
            $id = Arr::get($data, 'id');
            return $id;
        }
    }

    public function statisticBalance()
    {
        $apiKey = config('blockchain.x_api_key_wallet');
        $client = new \GuzzleHttp\Client(['verify' => false]);
        $url = config('blockchain.api_wallet') . ':' .  config('blockchain.port_wallet') . '/api/all/statistical_hotwallet';
        $response = $client->request('GET', $url, [
            'headers' => [
                "x-api-key" => $apiKey,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function totalBalance($currency)
    {
        return $this->statisticBalance()[$currency]['totalBalance'];
    }
}
