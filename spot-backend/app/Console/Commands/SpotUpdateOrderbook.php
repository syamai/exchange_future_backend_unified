<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\App;
use App\Consts;
use App\Models\Order;
use App\Models\Process;
use App\Http\Services\OrderService;
use Illuminate\Support\Facades\DB;

class SpotUpdateOrderbook extends SpotTradeBaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:update_orderbook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update orderbook';

    protected $process;
	protected $timeSleep = 10000;

    protected $orderService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->orderService = new OrderService();
    }


    protected function processTrade($trade)
    {
		if (!env("SEND_SOCKET_TRADE_PROCCESS", false)) {
			$buyOrder = Order::find($trade->buy_order_id);
			$sellOrder = Order::find($trade->sell_order_id);
			$this->orderService->sendUpdateOrderBookEvent(
				Consts::ORDER_BOOK_UPDATE_MATCHED,
				[$buyOrder, $sellOrder],
				$trade->quantity
			);
		}
    }

    protected function getProcessKey(): string
    {
        return 'update_spot_orderbook';
    }
}
