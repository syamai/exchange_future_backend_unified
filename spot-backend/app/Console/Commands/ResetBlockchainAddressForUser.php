<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ResetBlockchainAddressForUserJob;
use Illuminate\Support\Facades\DB;
use App\Models\CoinsConfirmation;
use Illuminate\Support\Facades\Schema;
use App\Consts;

class ResetBlockchainAddressForUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blockchain_address:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        DB::table('blockchain_addresses')->truncate();
        $coins = CoinsConfirmation::pluck('coin');
        foreach ($coins as $coin) {
            $table = $coin . '_accounts';
            if ($this->checkHasTable($table) && $coin !== 'usd') {
                $ids = DB::table($table)->whereNotNull('blockchain_address')->pluck('id');
                ResetBlockchainAddressForUserJob::dispatch($ids, $table)->onQueue(Consts::QUEUE_BLOCKCHAIN);
            }
        }
        echo "DONE";
    }

    private function checkHasTable($tableName)
    {
        return $hasTable = Schema::hasTable($tableName);
    }
}
