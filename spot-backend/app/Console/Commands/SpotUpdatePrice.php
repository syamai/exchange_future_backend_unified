<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\App;
use App\Consts;
use App\Models\Order;
use App\Models\Process;
use App\Http\Services\MasterdataService;
use App\Http\Services\PriceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SpotUpdatePrice extends SpotTradeBaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:update_price';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update price';

    protected $process;

    protected $priceService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->priceService = new PriceService();
    }

    protected function processTrade($trade)
    {
		if (!env("SEND_SOCKET_TRADE_PROCCESS", false)) {
			$this->priceService->updatePrice($trade);
		}
    }

    protected function getProcessKey(): string
    {
        return 'update_spot_price';
    }
}
