<?php

namespace App\Jobs;

use App\Utils\BigNumber;
use App\Events\OrderBookUpdated;
use App\Http\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendOrderBook extends RedisQueueJob
{
    private $currency;
    private $coin;
    private $tickerSize;

    private $orderService;

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
        $this->tickerSize = $data[2];

        $this->orderService = new OrderService();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // we have to start transaction in order to read with isolation level read uncomitted
        DB::connection('master')->beginTransaction();
        DB::connection('master')->getPdo()->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
        try {
            $orderBook = $this->orderService->getOrderBook($this->currency, $this->coin, $this->tickerSize, false, false);
            if ($this->shouldSendOrderbook($orderBook)) {
                event(new OrderBookUpdated($orderBook, $this->currency, $this->coin, $this->tickerSize, true));
            }
            DB::connection('master')->commit();
        } catch (\Exception $e) {
            DB::connection('master')->rollBack();
            Log::error($e);
            throw $e;
        }
    }

    private function shouldSendOrderbook($orderBook): bool
    {
        $topBuyPrice = $this->getTopPrice($orderBook['buy']);
        $topSellPrice = $this->getTopPrice($orderBook['sell']);
        if ($topBuyPrice && $topSellPrice) {
            return BigNumber::new($topBuyPrice)->comp($topSellPrice) < 0;
        }
        return true;
    }

    private function getTopPrice($rows)
    {
        if (count($rows) > 0) {
            return $rows[0]->price;
        } else {
            return 0;
        }
    }
}
