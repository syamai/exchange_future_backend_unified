<?php

namespace App\Events;

use App\Consts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class DisableTradingSpotByCircuitBreaker extends AppBroadcastEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $currency;
    public $coin;
    public $data;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($currency, $coin, $data)
    {
        $this->currency = $currency;
        $this->coin = $coin;
        $this->data = [
            'currency' => $currency,
            'coin' => $coin,
            'data' => $data,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel(Consts::SOCKET_CHANNEL_CIRCUIT_BREAKER);
    }
}
