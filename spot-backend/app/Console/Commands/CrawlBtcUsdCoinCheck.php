<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CrawlPriceBtcEthUsdCoinCheck;
use App\Consts;

class CrawlBtcUsdCoinCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coin_check_btc_eth_usd:crawl';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crawl Price BTC/USD and ETH/USD Coin Check';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        dispatch(new CrawlPriceBtcEthUsdCoinCheck())->onQueue(Consts::QUEUE_CRAWLER);
    }
}
