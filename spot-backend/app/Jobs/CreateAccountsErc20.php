<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Events\CreateAccountErc20 as CreateAccountsErc20Event;
use Illuminate\Support\Str;

class CreateAccountsErc20 implements ShouldQueue
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
     * @throws Exception
     */
    public function handle(): void
    {
        event(new CreateAccountsErc20Event());
        $coin = strtolower(Str::camel(data_get($this->params, 'coin_setting.symbol')));

        DB::beginTransaction();
        try {
            DB::insert("insert into {$coin}_accounts(id, blockchain_address) select id, blockchain_address from eth_accounts");
            DB::insert("insert into spot_{$coin}_accounts(id, blockchain_address) select id, blockchain_address from eth_accounts");
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());

            throw $e;
        }
    }
}
