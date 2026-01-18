<?php

namespace SotaWallet;

use App\Consts;
use App\Utils\BigNumber;
use Exception;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SotaWalletService
{
    const WITHDRAWAL_TYPE = 'withdrawal';
    const DEPOSIT_TYPE = 'deposit';

    public static function onReceiveTransaction($coin, $input)
    {
        Logger::info('SotaWalletService - Receive blockchain transaction: ', [$input]);

        $type = $input['type'];

        $transactionId = $input['data']['txid'];

        if ($type === self::WITHDRAWAL_TYPE) {
//            static::onReceiveWithdrawTransaction($transactionId, $input);
        } else {
            static::onReceiveDepositTransaction($coin, $transactionId);
        }
        return 'OK';
    }

    private static function onReceiveDepositTransaction($coin, $txHash)
    {
        try {
            $transactions = static::getDepositTransactions($coin, $txHash);

            foreach ($transactions as $originTransaction) {
                try {
                    $amount = static::fixTransactionAmount($originTransaction->valueString, $coin, true);

                    $params = [
                        'tx_hash' => $txHash,
                        'currency' => $coin,
                        'to_address' => $originTransaction->address,
                        'amount' => $amount,
                        'date' => $originTransaction->date,
                    ];

                    Logger::info('Transaction success:', [$txHash, $coin, $amount]);

                    static::webhookCallable($coin, [$txHash, $coin, $params]);
                } catch (Exception $e) {
                    Logger::error($e);
                }
            }
        } catch (Exception $e) {
            logger()->error("onReceiveDepositTransaction error: {$e->getMessage()}");
            Logger::error($e);
            throw $e;
        }
    }

    private static function webhookCallable($coin, ...$params)
    {
        $webhookCallable = config("webhook.$coin") ?? config("webhook.erc20");
        if ($webhookCallable) {
            return app()->call($webhookCallable, ...$params);
        }
    }

    private static function getRequiredConfirmations($coin)
    {
        return config("webhook.$coin.confirmations") ?? config("webhook.erc20.confirmations");
    }

    /**
     * @param $coin
     * @param $transactionId
     * @return array
     * @throws Exception
     */
    private static function getDepositTransactions($coin, $transactionId)
    {
        $transaction = static::getTransaction($coin, $transactionId);

        $walletId = static::getWalletId($coin);

        if ($transaction->confirmations < static::getRequiredConfirmations($coin)) {
            Logger::warning('SotaWalletService - Unconfirmed transaction:', [
                'transactionId' => $transactionId,
                'coin' => $coin,
                'wallet_id' => $walletId
            ]);
            throw new HttpException(422, __('exception.unconfirmed_transaction', compact('transactionId')));
        }

        $entries = [];
        foreach ($transaction->entries as $output) {
            if (property_exists($output, 'walletId') && $walletId == $output->walletId && BigNumber::new($output->value)->comp(0) > 0) {
                $output->date = $transaction->date;
                array_push($entries, $output);
            }
        }

        return $entries;
    }

    public static function getTransaction($coin, $transactionId)
    {
        try {
            $requestPath = "/api/{$coin}/tx/{$transactionId}";
            $response = SotaWalletRequest::sendRequest('GET', $requestPath);
            $transaction = json_decode($response->getBody()->getContents());

            Logger::info('SotaWalletService - Transaction info: ', [
                'transactionId' => $transactionId,
                'coin' => $coin,
                'content' => json_encode($transaction)
            ]);
            return $transaction;
        } catch (Exception $e) {
            logger()->error("getTransaction error sota wallet service: {$e->getMessage()}");
            // TODO: send a email to admin
            throw $e;
        }
    }

    public static function getAllCoins()
    {
        return array_merge(
            array_column(config('coin'), 'coin_shortcut'),
            Arr::pluck(config('coin.erc20'), 'coin_shortcut')
        );
    }

    public static function getWalletId($coin)
    {
        return config("coin.{$coin}.wallet_id") ?? config("coin.erc20.wallet_id");
    }

    public function generateAddress($coin)
    {
        return null;
    }


    public function send($amount, $coin, $toAddress, $tag = null, $fee = 0)
    {
        try {
            $amount = $this->fixTransactionAmount($amount, $coin, false);
            $walletId = $this->getWalletId($coin);
            $requestPath = "/api/$coin/wallet/$walletId/sendcoins";

            $body = [
                'address' => $toAddress,
                'amount' => $amount,
                'tag' => $tag,
                'fee' => $fee,
            ];
            $response = SotaWalletRequest::sendRequest('POST', $requestPath, $body);
            return json_decode($response->getBody()->getContents())->id;
        } catch (Exception $e) {
            Logger::error($e->getMessage());
            throw $e;
        }
    }

    /**
     * @param $coin
     * @param $walletId
     * @param $transactionId
     * @return mixed
     * @throws Exception
     */
    public function getBlockchainTransaction($coin, $walletId, $transactionId)
    {
        $requestPath = "/api/$coin/wallet/$walletId/tx/$transactionId";

        $response = SotaWalletRequest::sendRequest('GET', $requestPath);
        $body = $response->getBody();
        $transaction = json_decode($body->getContents());
        Logger::info("Transaction info: id: $transactionId, coin: $coin, wallet: $walletId, content: " . json_encode($transaction));
        return $transaction;
    }

    public static function fixTransactionAmount($value, $coin, $fromBlockchain)
    {
        $conversionRate = config("coin.{$coin}.conversion_rate") ?? config("coin.erc20.conversion_rate", 1);
        if ($fromBlockchain) {
            return BigNumber::new($value)->div($conversionRate)->toString();
        } else {
            return BigNumber::new($value)->mul($conversionRate)->toString();
        }
    }

    public static function trackingAddress($coin, $address)
    {
        try {
            $walletId = SotaWalletService::getWalletId($coin);
            $requestPath = "/api/$coin/wallet/$walletId/external_register";
            $response = SotaWalletRequest::sendRequest('POST', $requestPath, [
                'address' => $address
            ]);

            Logger::info('Tracking Address:', ['address' => $address, "response" => json_encode($response)]);
        } catch (Exception $e) {
            Logger::error('Tracking Address:', [$e->getMessage()]);
            throw $e;
        }
    }

    public static function getAddressBalance($coin, $address)
    {
        try {
            $requestPath = "/api/$coin/balance/$address";

            $response = SotaWalletRequest::sendRequest('GET', $requestPath);
            return json_decode($response->getBody()->getContents());
        } catch (Exception $e) {
            Logger::error('getAddressBalance:', [$e->getMessage()]);
            throw $e;
        }
    }

    public static function registerErc20($params, $network = null)
    {
        try {
            $requestPath = "/api/erc20_tokens";
            if ($network && strtolower($network) == Consts::TYPE_TOKEN_BNB) {
                $requestPath = "/api/bep20_tokens";
            }

            $response = SotaWalletRequest::sendRequest('POST', $requestPath, $params);
            return json_decode($response->getBody()->getContents());
        } catch (Exception $e) {
            Logger::error('registerErc20:', [$e->getMessage()]);
            throw $e;
        }
    }

    public static function deleteErc20($contractAddress)
    {
        try {
            $requestPath = "/api/erc20_tokens/delete";

            $response = SotaWalletRequest::sendRequest('POST', $requestPath, ['contract_address' => $contractAddress]);
            return json_decode($response->getBody()->getContents());
        } catch (Exception $e) {
            Logger::error('deleteErc20:', [$e->getMessage()]);
            throw $e;
        }
    }

    public static function getErc20ContractAddressInformation($contractAddress)
    {
        try {
            $requestPath = "/api/eth/get_currency";

            $params = [
                'contract_address' => $contractAddress
            ];

            $response = SotaWalletRequest::sendRequest('POST', $requestPath, $params);
            return json_decode($response->getBody()->getContents());
        } catch (Exception $e) {
            Logger::error('getErc20ContractAddressInformation:', [$e->getMessage()]);
            throw $e;
        }
    }
    public static function getErc20Information($contractAddress, $network = null)
    {
        try {
            $requestPath = "/api/currency_config/erc20.{$contractAddress}";
            if ($network && strtolower($network) == Consts::TYPE_TOKEN_BNB) {
                $requestPath = "/api/currency_config/bep20.{$contractAddress}";
            }

            $response = SotaWalletRequest::sendRequest('GET', $requestPath);
            return json_decode($response->getBody()->getContents());
        } catch (Exception $e) {
            Logger::error('getErc20ContractAddressInformation:', [$e->getMessage()]);
            throw $e;
        }
    }
}
