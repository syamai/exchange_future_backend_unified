<?php

namespace App\Jobs;

use App\Consts;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Events\FinishedRegisterErc20 as FinishedRegisterErc20Event;
use Illuminate\Support\Arr;

class FinishRegisterErc20 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $params;

    /**
     * Create a new job instance.
     *
     * @param $params
     */
    public function __construct($params)
    {
        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $tradingPairs = Arr::get($this->params, 'trading_setting', []);
        foreach ($tradingPairs as $index => $tradingPair) {
            $coin = strtolower(Arr::get($tradingPair, 'coin'));
            $currency = strtolower(Arr::get($tradingPair, 'currency'));
            StartNewOrderProcessor::dispatch($coin, $currency)->delay($index * 2)->onQueue(Consts::ADMIN_QUEUE);
        }

        event(new FinishedRegisterErc20Event);
    }
}
