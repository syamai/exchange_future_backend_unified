<?php

namespace App\Jobs;

use App\Consts;
use App\Utils;
use App\Events\PricesUpdated;
use App\Http\Services\MasterdataService;
use App\Http\Services\OrderService;
use App\Http\Services\PriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendPrices extends RedisQueueJob
{
    private $currency;
    private $coin;

    private $priceService;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($json)
    {
        $data = json_decode($json);
        $this->currency = $data[0];
        $this->coin = $data[1];

        $this->priceService = new PriceService();
    }

    /*protected static function getNextRun()
    {
        return static::currentMilliseconds() + 50;
    }*/

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $loadCache = false;
        $price = $this->priceService->getSinglePrice($this->currency, $this->coin, $loadCache);
        event(new PricesUpdated($price));
    }
}
