<?php

namespace App\Jobs;

use App\Events\PricesTickerUpdated;
use App\Http\Services\PriceService;

class SendPricesChange extends RedisQueueJob
{

    private $priceService;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($json)
    {
        $this->priceService = new PriceService();
    }

    protected static function getNextRun()
    {
        return static::currentMilliseconds() + 300;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $price = $this->priceService->getPrices();
        event(new PricesTickerUpdated($price));
    }
}
