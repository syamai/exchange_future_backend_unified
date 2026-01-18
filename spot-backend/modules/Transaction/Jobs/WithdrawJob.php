<?php

namespace Transaction\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Transaction\Http\Services\WithdrawJobService;
use Illuminate\Support\Facades\Log;

class WithdrawJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    private $transaction, $withdrawJobService;

    public $tries = 1;

    public function __construct($transaction)
    {
        $this->transaction = $transaction;
        $this->withdrawJobService = new WithdrawJobService();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            logger()->info('WithdrawJob Request ==============' . $this->transaction->id);
            $this->withdrawJobService->approveWallet($this->transaction);
        } catch (\Exception $e) {
            Log::error($e);
            return;
        }
    }
}
