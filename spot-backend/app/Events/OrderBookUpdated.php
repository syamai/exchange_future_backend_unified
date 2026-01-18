<?php

namespace App\Events;

use App\Consts;
use App\Utils\BigNumber;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class OrderBookUpdated extends AppBroadcastEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

	private $keyChannelOrder;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($orderBook, $currency, $coin, $tickerSize, $isFullOrderBook)
    {
        $this->data = [
            'currency' => $currency,
            'coin' => $coin,
            'tickerSize' => $tickerSize,
            'isFullOrderBook' => $isFullOrderBook,
            'orderBook' => $orderBook
        ];
        //$this->keyChannelOrder = ".${currency}.${coin}.".BigNumber::new($tickerSize)->toString();
		$this->keyChannelOrder = ".${currency}.${coin}.${tickerSize}";
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('App.OrderBook'.$this->keyChannelOrder);
    }
}
