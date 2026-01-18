<?php

namespace App\Events;

use App\Consts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class OrderTransactionCreated extends AppBroadcastEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($orderTransaction, $buyOrder, $sellOrder)
    {
        $this->modifyData($orderTransaction, $buyOrder, $sellOrder);

        $this->data = [
            'orderTransaction' => $orderTransaction,
            'buyOrder' => $buyOrder,
            'sellOrder' => $sellOrder,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('App.Orders');
    }

    private function modifyData($orderTransaction, $buyOrder, $sellOrder)
    {
        self::removeAttribute($orderTransaction, 'buyer_email');
        self::removeAttribute($orderTransaction, 'seller_email');

        self::removeAttribute($buyOrder, 'email');
        self::removeAttribute($sellOrder, 'email');
    }

    private function removeAttribute($object, $attribute)
    {
        unset($object[$attribute]);
    }
}
