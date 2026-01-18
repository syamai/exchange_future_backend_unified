<?php

namespace App\Jobs;

use App\Consts;
use App\Utils;
use App\Events\OrderListUpdated;
use App\Http\Services\UserService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendOrderList extends RedisQueueJob
{
    private $userId;
    private $currency;
    private $action;

    private $userService;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($json)
    {
        $data = json_decode($json);
        $this->userId = $data[0];
        $this->currency = $data[1];
        $this->action = $data[2];

        $this->userService = new UserService();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        parent::handle();
        event(new OrderListUpdated($this->userId, $this->currency, $this->action));
    }
}
