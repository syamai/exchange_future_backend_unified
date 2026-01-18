<?php

namespace App\Console\Commands;

use App\Jobs\CrawlCoinMarketCapTicker;
use Illuminate\Console\Command;
use App\Consts;

class UpdateCoinMarketCapTicker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coin_market_cap_ticker:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crawl coin market cap ticker';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        dispatch(new CrawlCoinMarketCapTicker())->onQueue(Consts::QUEUE_CRAWLER);
    }
}
