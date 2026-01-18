<?php

namespace App\Jobs;

use App\Consts;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SotaWallet\SotaWalletService;

class RegisterWalletErc20 implements ShouldQueue
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
            $contractAddress = data_get($this->params, 'coin_setting.contract_address');
            $params = [
                "custom_symbol" => data_get($this->params, 'coin_setting.symbol'),
                "contract_address" => $contractAddress
            ];
            SotaWalletService::registerErc20($params, data_get($params, 'coin_setting.network'));

            return UpdateMasterdataErc20::dispatch($this->params)->onQueue(Consts::ADMIN_QUEUE);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            if ($e->getCode() === 400 && Str::contains($e->getMessage(), 'already existed.')) {
                return UpdateMasterdataErc20::dispatch($this->params)->onQueue(Consts::ADMIN_QUEUE);
            }

            throw $e;
        }
    }
}
