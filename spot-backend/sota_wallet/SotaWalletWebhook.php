<?php

namespace SotaWallet;

use Exception;

class SotaWalletWebhook
{
    public static function getDefaultWebhookUrl()
    {
        return '/api/webhook/sotatek';
    }

    public static function registerWebhook($url)
    {
        $requestPath = '/api/webhooks';
        $body = [ 'url' => $url ];

        $response = SotaWalletRequest::sendRequest('POST', $requestPath, $body);
        return $response->getBody()->getContents();
    }

    public static function removeWebhook($url)
    {
        $requestPath = '/api/webhooks';
        $body = [
            'url' => $url,
        ];

        $response = SotaWalletRequest::sendRequest('DELETE', $requestPath, $body);
        return $response->getBody()->getContents();
    }

    public static function getWebhooks()
    {
        try {
            $requestPath = "/api/webhooks";

            $response = SotaWalletRequest::sendRequest('GET', $requestPath);

            return json_decode($response->getBody()->getContents());
        } catch (Exception $e) {
            Logger::error($e->getMessage());
        }
    }
}
