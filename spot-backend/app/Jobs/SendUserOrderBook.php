<?php

namespace App\Jobs;

use App\Events\UserOrderBookUpdated;
use App\Http\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendUserOrderBook extends RedisQueueJob
{
    private $userId;
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
        $this->userId = $data[0];
        $this->currency = $data[1];
        $this->coin = $data[2];
        $this->tickerSize = $data[3];

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
            $orderBook = $this->orderService->getUserOrderbook($this->userId, $this->currency, $this->coin, $this->tickerSize);
            event(new UserOrderBookUpdated($orderBook, $this->userId, $this->currency, $this->coin, $this->tickerSize));
            DB::connection('master')->commit();
        } catch (\Exception $e) {
            DB::connection('master')->rollBack();
            Log::error($e);
            throw $e;
        }
    }
}
