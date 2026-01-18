<?php

namespace SotaWallet;

class SotaWalletRequest
{
    public static function sendRequest($method, $path, $body = null)
    {
        $apiKey = config('blockchain.x_api_key_wallet');

        $client = new \GuzzleHttp\Client(['verify' => false]);
        $url = config('blockchain.api_wallet') . ':' . config('blockchain.port_wallet') . $path;

        try {
            $res = $client->request($method, $url, [
                'headers' => [
                    "x-api-key" => $apiKey,
                ],
                'connect_timeout' => 10000,
                \GuzzleHttp\RequestOptions::JSON => $body
            ]);

            return $res;
        } catch (\Exception $e) {
            Logger::error("Send request $url: " .  $e->getMessage());
            throw $e;
        }
    }
}
