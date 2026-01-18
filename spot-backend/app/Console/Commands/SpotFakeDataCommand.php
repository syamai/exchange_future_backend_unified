<?php

namespace App\Console\Commands;

use App\Consts;
use App\Jobs\SpotFakeData;
use Illuminate\Console\Command;

class SpotFakeDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:fake_data {currency} {coin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Spot fake data command';



    /**
     * Execute the console command.
     *
     */
    protected $checkingInterval;
    public function handle()
    {
        $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
        if ($matchingJavaAllow) {
            return Command::SUCCESS;
        }

        $fakeDataTradeSpot = env("FAKE_DATA_TRADE_SPOT", false);
        if (!$fakeDataTradeSpot) {
            return Command::SUCCESS;
        }
        $currency = $this->argument('currency') ?? '';
        $coin = $this->argument('coin') ?? '';
        //echo "\nSpotFakeDataCommand:".$currency."-".$coin;
        if (!isset(Consts::FAKE_CURRENCY_COINS[$coin . '_' . $currency])) {
            return Command::SUCCESS;
        }
        SpotFakeData::dispatch($currency, $coin)->onQueue("fake_data")->onConnection(Consts::CONNECTION_SOCKET);
    }
}
