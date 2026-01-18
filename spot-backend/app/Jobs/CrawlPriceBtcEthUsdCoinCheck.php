<?php

namespace App\Jobs;

use App\Events\CoinCheckBtcUsdExchangesUpdated;
use App\Consts;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\CoinMarketCapTicker;
use App\Utils\BigNumber;
use GuzzleHttp\Client;
use App\Http\Services\CoinMarketCapService;

use Exception;

class CrawlPriceBtcEthUsdCoinCheck implements ShouldQueue
{
    const CACHE_LIVE_TIME = 120; // 2 minutes
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     * @internal param Client $client
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::beginTransaction();
        try {
            $service = new CoinMarketCapService();
            $service->updateDataBase();
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            logger()->error($e->getMessage());
            throw $e;
        }
    }
}
