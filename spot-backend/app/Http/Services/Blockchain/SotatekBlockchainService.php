<?php

namespace App\Http\Services\Blockchain;

use App\Consts;
use App\Facades\FormatFa;
use App\Http\Services\FirebaseNotificationService;
use App\Http\Services\TransactionService;
use App\Jobs\SendFutureFirebaseNotification;
use App\Models\User;
use App\Utils;
use App\Utils\BigNumber;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Transaction\Utils\UpdateBalance;
use Illuminate\Support\Facades\DB;

class SotatekBlockchainService
{
    private $transactionService;
    private $count;
    private $coinConfig;
    private $networkId;

    public function __construct($networkCoin, $count = 1)
    {
        $this->count = $count;
        $this->transactionService = new TransactionService();
        if (is_string($networkCoin)) {
            $networkCoin = (object)[
                'coin' => $networkCoin,
                'network_id' => null
            ];
        }

        $coinNetwork = FormatFa::formatCoinNetwork($networkCoin->coin);
        if ($coinNetwork) {
            $this->networkId = empty($networkCoin->network_id) ? $coinNetwork->network_id : $networkCoin->network_id;
            $this->coinConfig = CoinConfigs::getCoinConfig($coinNetwork->coin, $this->networkId);
        }

    }

    public function getWalletInfo(): array
    {
        $response = $this->requestAPI('GET', $this->coinConfig->getRequestPathWalletInfo());
        $data = json_decode($response->getBody()->getContents());

        return [
            'blockchain_address' => $data->id,
            'balance' => $this->fixTransactionAmount($data->balanceString, true)
        ];
    }

    public function getNetworkId()
    {
        return $this->networkId;
    }

    public function createAddress()
    {
        $url = $this->coinConfig->getApiCreateAddress();
        $body = ['amount' => $this->count];

        $response = $this->requestCreateAddress('POST', $url, $body);
        $body = json_decode($response->getBody()->getContents());

        if ($this->coinConfig->isTagAttached()) {
            return $body->address;
        } else {
            $addresses = array_map(function ($item) {
                return $item;
            }, $body);

            return $addresses;
        }
    }

    private function requestCreateAddress($method, $url, $body)
    {
        // $accessToken = config('blockchain.sb_token');
        $apiKey = config('blockchain.x_api_key_wallet');
        $client = new \GuzzleHttp\Client(['verify' => false]);

        return $client->request($method, $url, [
            'headers' => [
                // 'Authorization' => "Bearer $accessToken",
                "x-api-key" => $apiKey,
            ],
            \GuzzleHttp\RequestOptions::JSON => $body
        ]);
    }

    public function send($amount, $toAddress, $tag, $fee)
    {
        $amount = $this->fixTransactionAmount($amount, false);
        $requestPath = $this->coinConfig->getRequestPathToSend();

        $body = [
            'address' => $toAddress,
            'amount' => $amount,
        ];

        if ($tag) {
            $body['tag'] = $tag;
        }

        $response = $this->requestAPI('POST', $requestPath, $body);

        return json_decode($response->getBody()->getContents())->id;
    }

    public function onReceiveTransaction($input): int|string
    {
        $type = $input['type'];

        $tx_hash = $input['data']['txid'];

        if ($type === 'withdrawal') {
            logger(__FUNCTION__, ['type' => 'withdraw']);
            $this->onReceiveWithdrawTransaction($tx_hash, $input);
        } else {
            logger(__FUNCTION__, ['type' => 'deposit']);
            if ($input['event'] == 'collected') {
                $this->updateCollectCoinStatus($tx_hash);
            }
            try {
                DB::table('processed_transactions')->insert([
                    'tx_hash' => $tx_hash,
                    'currency' => $input['data']['currency'],
                ]);
            } catch (Exception $e) {
                Log::error("Existing transaction: tx_hash: $tx_hash");
                logger($e);
                return 0;
            }

            $this->onReceiveDepositTransaction($tx_hash);
            if ($input['event'] == 'collected') {
                $this->updateCollectCoinStatus($tx_hash);
            }
        }

        return 'OK';
    }

    private function onReceiveWithdrawTransaction($tx_hash, $params): void
    {
        logger(__FUNCTION__, $params);

        $coin = $this->coinConfig->getCoin();
        $tmpWithdrawId = $params['data']['id'];

        if ($params['event'] === Consts::WEBHOOK_COMPLETED_STATUS) {
            $transaction = $this->getTransaction($tx_hash);

            logger(__FUNCTION__, (array)($transaction));

            if ($transaction->confirmations < $this->coinConfig->getRequiredConfirmations()) {
                throw new HttpException(422, 'Confirmation is not enough');
            }

            $withdrawTransaction = $this->transactionService
                ->updateTransactionStatus($coin, $tmpWithdrawId, $tx_hash, Consts::TRANSACTION_STATUS_SUCCESS);

            if ($withdrawTransaction) {
                $user = User::query()->find($withdrawTransaction->user_id);
                $locale = $user->getLocale();
                $title = __('title.notification.withdraw_success', [], $locale);
                $body = __('body.notification.withdraw_success', ['time' => Carbon::now()], $locale);

                app(UpdateBalance::class)->verifyWithdrawExternal($withdrawTransaction);
                FirebaseNotificationService::send($user->id, $title, $body);
                app(\Transaction\Http\Services\TransactionService::class)
                    ->transactionBalanceEvent($withdrawTransaction->currency, $withdrawTransaction->user_id);
                $amountWithdraw = $withdrawTransaction->amount;
                if ($amountWithdraw < 0) {
                    $amountWithdraw = BigNumber::new($amountWithdraw)->mul(-1)->toString();
                }
                SendFutureFirebaseNotification::dispatch([
                    'type' => 'WITHDRAW',
                    'data' => [
                        'amount' => $amountWithdraw,
                        'coinType' => $withdrawTransaction->currency,
                        'time' => Utils::currentMilliseconds(),
                        'address' => $withdrawTransaction->to_address
                    ]
                ]);
            }
        }

        if ($params['event'] === Consts::WEBHOOK_FAILED_STATUS) {
            if ($tx_hash == Consts::WEBHOOK_FAILED_STATUS) {
                $tx_hash = '';
            } else {
                $transaction = $this->getTransaction($tx_hash);

                logger()->info('TRANSACTION EVENT FAILED=======' . json_encode($transaction));

                if ($transaction->confirmations < $this->coinConfig->getRequiredConfirmations()) {
                    throw new HttpException(422, 'Confirmation is not enough');
                }
            }


            $withdrawTransaction = $this->transactionService
                ->updateTransactionStatus($coin, $tmpWithdrawId, $tx_hash, Consts::TRANSACTION_STATUS_ERROR);

            if ($withdrawTransaction) {
                $user = User::query()->find($withdrawTransaction->user_id);
                $userBalance = $this->transactionService->getAndLockUserBalance($withdrawTransaction->currency,
                    $user->id);
                $updateAvailableBalance = BigNumber::new($withdrawTransaction->amount)->abs();

                $this->transactionService->refundUserBalance($userBalance, $withdrawTransaction->currency,
                    $withdrawTransaction, true, $updateAvailableBalance);

                app(\Transaction\Http\Services\TransactionService::class)
                    ->transactionBalanceEvent($withdrawTransaction->currency, $withdrawTransaction->user_id);
            }
        }
    }

    private function getAddress($transaction)
    {
        $address = $transaction->address;

        if ($this->coinConfig->isTagAttached()) {
            $destinationTag = $transaction->destinationTag;
            $address .= Consts::XRP_TAG_SEPARATOR . $destinationTag;
        }

        return $address;
    }

    private function onReceiveDepositTransaction($tx_hash): void
    {
        $transactions = $this->getDepositTransactions($tx_hash);
        logger('-------------------------------: ' . $tx_hash);
        logger(json_encode($transactions));

        $coin = $this->coinConfig->getCoin();
        $networkId = $this->coinConfig->getNetworkId();
        $result = [];
        foreach ($transactions as $transaction) {
            $address = $this->getAddress($transaction);
            $amount = $this->fixTransactionAmount($transaction->valueString, true);
            if ($transaction->date) {
                $createdAt = Utils::dateTimeToMilliseconds($transaction->date);
            } else {
                $createdAt = Carbon::now()->timestamp * 1000;
            }

            $result[] = $this->transactionService->deposit($address, $tx_hash, $amount, $coin, $networkId, $createdAt);
        }


        $countError = 0;

        foreach ($result as $value) {
            if ($value === 'error') {
                $countError++;
            }
        }

        if ($countError === count($transactions)) {
            logger(__('exception.deposit_non_exist', compact('address')));
        }
    }

    private function updateCollectCoinStatus($tx_hash): void
    {
        $transactions = $this->getDepositTransactions($tx_hash);
        logger('-------------------------------: ' . $tx_hash);
        logger(json_encode($transactions));

        $coin = $this->coinConfig->getCoin();
        $result = [];
        foreach ($transactions as $transaction) {
            $address = $this->getAddress($transaction);
            //to do : add job here after check result function updateCollectStatus
            $rs = $this->transactionService->updateCollectStatus($address, $tx_hash, $coin);
            $result[] = $rs;
            // if ($rs) {
            //     UpdateTotalVolumeDepositJob::dispatch($transaction)->onQueue(Consts::QUEUE_UPDATE_DEPOSIT);
            // }
        }

        $countError = 0;

        foreach ($result as $value) {
            if ($value === 'error') {
                $countError++;
            }
        }

        if ($countError === count($transactions)) {
            logger(__('exception.deposit_non_exist', compact('address')));
        }
    }


    public function getTransaction($tx_hash)
    {
        $requestPath = "{$this->coinConfig->getCoinRequestPathTransaction()}/{$tx_hash}";

        $response = $this->requestAPI('GET', $requestPath);
        $body = $response->getBody();
        $transaction = json_decode($body->getContents());

        return $transaction;
        // $fakedata = '{
        //   "id": "0x8efef2c7f8ad881d86417658fa7559adb4c5ef2c6d2441f85742f434861e1c1d",
        //   "date": "",
        //   "timestamp": 1581515933,
        //   "blockHash": "0xb106815dde81904994b6b937502a8837e173a42f6abcd888b5d766606ee8322e",
        //   "blockHeight": 9468555,
        //   "confirmations": 12175,
        //   "entries": [
        //     {
        //       "address": "0xC126Ead2E1B0e490541b969157Bd109C7C3463E6",
        //       "value": "-4000000000000000",
        //       "valueString": "-4000000000000000"
        //     },
        //     {
        //       "address": "0x86616Ac136c9d3b8bd69680bfFe427BDc9a1b6b5",
        //       "value": "4000000000000000",
        //       "valueString": "4000000000000000"
        //     }
        //   ]
        // }';

        // $fake_transaction = json_decode($fakedata);
        // return $fake_transaction;
    }

    /**
     * @param $tx_hash
     * @return array
     * @throws Exception
     */
    private function getDepositTransactions($tx_hash): array
    {
        $transaction = $this->getTransaction($tx_hash);

        $coin = $this->coinConfig->getCoin();

        if ($transaction->confirmations < $this->coinConfig->getRequiredConfirmations()) {
            Log::error("Unconfirm $tx_hash: {$tx_hash}, coin: {$this->coinConfig->getCoin()}");
            throw new HttpException(422, __('exception.unconfirm_transaction'));
        }

        $entries = [];

        foreach ($transaction->entries as $output) {
            if (BigNumber::new($output->value)->comp(0) > 0) {
                $output->date = $transaction->date;
                $output->tx_hash = $transaction->blockHash;

                switch ($coin) {
                    case Consts::CURRENCY_XRP:
                        $output->destinationTag = $transaction->destinationTag;
                        break;
                    case Consts::CURRENCY_EOS:
                        $output->destinationTag = $transaction->memo;
                    case Consts::CURRENCY_TRX:
                        $output->destinationTag = $transaction->memo;
                }
                array_push($entries, $output);
            }
        }

        return $entries;
    }

    public function getDefaultWebhookUrl(): string
    {
        return '/api/webhook/sotatek';
    }

    private function getRequestPathRegisterWebhook(): string
    {
        return '/api/webhooks';
    }

    public function registerWebhook($url): string
    {
        $requestPath = $this->getRequestPathRegisterWebhook();

        $body = ['url' => $url];

        $response = $this->requestAPI('POST', $requestPath, $body);
        return $response->getBody()->getContents();
    }

    public function removeWebhook($url): string
    {
        $requestPath = $this->getRequestPathRegisterWebhook();

        $body = ['url' => $url];

        $response = $this->requestAPI('DELETE', $requestPath, $body);
        return $response->getBody()->getContents();
    }

    public function getColdWallet(): string
    {
        $requestPath = $this->coinConfig->getRequestPathToGetColdWallet();
        $response = $this->requestAPI('GET', $requestPath);
        return $response->getBody()->getContents();
    }

    public function getColdWalletByCoin(): string
    {
        $coin = $this->coinConfig->getCurrentTx();
        $requestPath = $this->coinConfig->getRequestPathToGetColdWalletByCoin($coin);
        $response = $this->requestAPI('GET', $requestPath);
        return $response->getBody()->getContents();
    }

    public function updateColdWallet($body): string
    {
        $requestPath = $this->coinConfig->getRequestPathToUpdateColdWallet();
        $response = $this->requestAPI('POST', $requestPath, $body);
        return $response->getBody()->getContents();
    }

    public function resetColdWallet(): string
    {
        $requestPath = $this->coinConfig->getRequestPathToResetColdWallet();
        $response = $this->requestAPI('GET', $requestPath);
        return $response->getBody()->getContents();
    }

    public function updateMailColdWallet($body): string
    {
        $requestPath = $this->coinConfig->getRequestPathToUpdateMailV2();
        $response = $this->requestAPI('POST', $requestPath, $body);
        return $response->getBody()->getContents();
    }

    public function getWalletBalance(): string
    {
        $requestPath = $this->coinConfig->getRequestPathToGetWalletBalance();
        $response = $this->requestAPI('GET', $requestPath);
        return $response->getBody()->getContents();
    }

    public function isSupportedCoin(): bool
    {
        return !!$this->coinConfig;
    }

    public function isToken(): bool
    {
        return $this->isSupportedCoin() && $this->coinConfig->isToken();
    }

    public function isEthErc20Token(): bool
    {
        return $this->isSupportedCoin() && $this->coinConfig->isEthErc20Token();
    }

    public function isBEP20Token(): bool
    {
        return $this->isSupportedCoin() && $this->coinConfig->isBEP20Token();
    }

    private function requestAPI($method, $path, $body = null): \Psr\Http\Message\ResponseInterface
    {
        // $accessToken = config('blockchain.sb_token');
        $apiKey = config('blockchain.x_api_key_wallet');

        $client = new \GuzzleHttp\Client(['verify' => false]);

        $res = $client->request($method, config('blockchain.api_wallet') . $path, [
            'headers' => [
                "x-api-key" => $apiKey,
            ],
            \GuzzleHttp\RequestOptions::JSON => $body
        ]);
        return $res;
    }

    public function fixTransactionAmount($amount, $fromBlockchain)
    {
        $conversionRate = $this->coinConfig->getConversionRate();

        if ($fromBlockchain) {
            return BigNumber::new($amount)->div($conversionRate, BigNumber::ROUND_MODE_FLOOR)->toString();
        } else {
            return BigNumber::new($amount)->mul($conversionRate)->toString();
        }
    }
}
