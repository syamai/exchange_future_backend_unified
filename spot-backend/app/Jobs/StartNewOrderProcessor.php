<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Redis;

class StartNewOrderProcessor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $coin;
    protected $currency;

    /**
     * Create a new job instance.
     *
     * @param $coin
     * @param $currency
     */
    public function __construct($coin, $currency)
    {
        $this->coin = $coin;
        $this->currency = $currency;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Redis::publish('StartNewOrderProcessor', collect([
            'coin' => $this->coin,
            'currency' => $this->currency
        ])->toJson());
    }
}
