<?php

namespace App\Events;

use App\Consts;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class UserWithdrawalSettingEvent extends AppBroadcastEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $data;
    private $userId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($userId, $data)
    {
        $this->userId = $userId;
        $this->data = json_encode($data);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel(Consts::SOCKET_CHANNEL_USER.$this->userId);
    }
}
