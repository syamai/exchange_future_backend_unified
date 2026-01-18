<?php

namespace App\Console\Commands;

use App\Http\Services\CoinMarketCapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrawlCoinMarketCap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:price';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crawl All Price from Coin Market Cap';

    /**
     * Execute the console command.
     *
     * @throws \Exception
     */
    public function handle()
    {
        DB::beginTransaction();
        try {
            $coinMarketCapService = new CoinMarketCapService();
            $coinMarketCapService->crawlPrice();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            throw $e;
        }
    }
}
