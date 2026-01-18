<?php

namespace App\Console\Commands;

use App\Consts;
use App\Jobs\SpotPlaceOrder;
use Illuminate\Console\Command;

class SpotPlaceDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:place_order {currency} {coin} {type?}';

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
        $userAutoMatching = env('FAKE_USER_AUTO_MATCHING', 1);
        if ($userAutoMatching) {
            $currency = $this->argument('currency') ?? '';
            $coin = $this->argument('coin') ?? '';
            $type = $this->argument('type') ?? 'price';
            if (in_array($type, ['price', 'bid', 'ask', 'cancel', 'test'])) {
                SpotPlaceOrder::dispatch($type, $currency, $coin, $userAutoMatching)->onQueue("place_order")->onConnection(Consts::CONNECTION_SOCKET);
            }

        }

    }
}
