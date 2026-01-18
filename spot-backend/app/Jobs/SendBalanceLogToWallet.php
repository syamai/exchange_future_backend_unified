<?php

namespace App\Jobs;

use App\Models\User;
use App\Consts;
use App\Events\TransactionCreated;
use App\Http\Services\TransactionService;
use App\Notifications\WithdrawErrorsAlerts;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Transaction\Models\Transaction;
use App\Utils\BigNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class SendBalanceLogToWallet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $data = null;

    /**
     * Create a new job instance.
     * @param $data
     */
    public function __construct( $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            logger()->info('Send Balance Log to Wallet Request ==============' . json_encode($this->data));
            $client = new Client();
            if (isset($this->data['userId']) && !isset($this->data['accountId'])) {
                $user = User::find($this->data['userId']);
                if ($user) {
                    $this->data['accountId'] = $user->uid;
					if (env('DISABLE_BOT_SEND_BALANCE_LOG_TO_WALLET', false) && $user->type == Consts::USER_TYPE_BOT) {
						return false;
					}
                }
            }

            $apiUri = config('blockchain.api_wallet') . ':' . config('blockchain.port_wallet');
            $path = $apiUri . "/api/v1/kafka/user-balance-log";
            $apiKey = config('blockchain.x_api_key_wallet');

            $response = $client->post($path, [
                'headers' => [
                    "x-api-key" => $apiKey,
                ],
                'json' => $this->data,
                'timeout' => 20
            ]);
            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);
                return Arr::get($data, 'id');
            }

        } catch (\Exception $e) {
            logger()->error("REQUEST SEND BALANCE LOG TO WALLET FAIL ======== " . $e->getMessage());
            throw $e;
        }
    }
}
