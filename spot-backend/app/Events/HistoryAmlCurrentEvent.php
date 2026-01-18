<?php

  namespace App\Events;

  use Illuminate\Broadcasting\Channel;
  use Illuminate\Queue\SerializesModels;
  use Illuminate\Foundation\Events\Dispatchable;
  use Illuminate\Broadcasting\InteractsWithSockets;
  use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class HistoryAmlCurrentEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */

    public $history;

    public function __construct($history)
    {
        $this->history = $history;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('amal');
    }

    public function broadcastAs()
    {
        return 'history';
    }

    public function broadcastWith()
    {
        return $this->history;
    }
}
