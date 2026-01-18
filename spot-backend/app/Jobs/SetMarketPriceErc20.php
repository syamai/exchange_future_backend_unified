<?php

namespace App\Jobs;

use App\Consts;
use App\Utils;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Events\SetMarketPriceErc20 as SetMarketPriceErc20Event;

class SetMarketPriceErc20 implements ShouldQueue
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
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        event(new SetMarketPriceErc20Event());

        $tradingPairs = Arr::get($this->params, 'trading_setting', []);

        DB::beginTransaction();
        try {
            foreach ($tradingPairs as $tradingPair) {
                DB::table("prices")->insert([
                    'currency' => strtolower(Arr::get($tradingPair, 'currency')),
                    'coin' => strtolower(Arr::get($tradingPair, 'coin')),
                    'price' => Arr::get($tradingPair, 'market_price'),
                    'quantity' => '0',
                    'amount' => '0',
                    'created_at' => Utils::currentMilliseconds()
                ]);
            }

            CreateAccountsErc20::dispatch($this->params)->onQueue(Consts::ADMIN_QUEUE);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());

            throw $e;
        }
    }
}
