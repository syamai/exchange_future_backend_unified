<?php

namespace App\Jobs;

use App\Consts;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Exception;
use SotaWallet\SotaWalletRequest;
use Illuminate\Support\Facades\Log;

class RegisterTokenNetworkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $params;

    /**
     * Create a new job instance.
     *
     * @param $params
     */
    public function __construct($params)
    {
        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return \Illuminate\Foundation\Bus\PendingDispatch
     * @throws Exception
     */
    public function handle()
    {
        try {
            $contractAddress = data_get($this->params, 'contract_address');
            $params = [
                "contract_address" => $contractAddress
            ];
            $networkSymbol = data_get($this->params, 'symbol');
            
            $requestPath = "/api/". $networkSymbol . "_tokens";
            $response = SotaWalletRequest::sendRequest('POST', $requestPath, $params);

            return json_decode($response->getBody()->getContents());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }
}
