<?php

namespace App\Console\Commands;

use App\Consts;
use App\Models\User;
use App\Http\Services\MasterdataService;
use App\Jobs\CreateUserAccounts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixUserAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user_account:fix {id} {--confirm=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $id = $this->argument('id');
        $user = User::find($id);
        if (!$user) {
            $this->info('User not found');
            return;
        }

        $prefixs = [
            'main' => '',
            //'margin' => Consts::TYPE_MARGIN_BALANCE . '_',
            'spot' => Consts::TYPE_EXCHANGE_BALANCE . '_',
            //'mam' => Consts::TYPE_MAM_BALANCE . '_',
            'airdrop' => Consts::TYPE_AIRDROP_BALANCE . '_',
        ];

        foreach ($prefixs as $key => $prefix) {
            $this->info('BALANCE_TYPE: ' . $key);
            if ($key != 'airdrop') {
                $this->checkHasOtherCoinAccount($id, $prefix);
            } else {
                $this->checkHasAirdropAccount($id);
            }
        }

        // Check Margin Account
        $this->info('BALANCE_TYPE: margin');
//        $this->checkHasMarginAccount($id);

        if ($this->option('confirm') === 'yes' || $this->confirm("Do you want to continue?")) {
            CreateUserAccounts::dispatch($id)->onQueue(Consts::QUEUE_BLOCKCHAIN);
        }
    }

    private function checkHasOtherCoinAccount($id, $prefix)
    {
        $tableName = $prefix.'usd_accounts';
        if (!$this->checkHasTable($tableName)) {
            $this->error('Table ' . $tableName.' is NOT exist');
            return false;
        }

        $usdAccount = DB::table($tableName)->where('id', $id)->first();
        if ($usdAccount) {
            $this->info(json_encode($usdAccount));
        } else {
            $this->error('Usd account is NOT found');
        }
        foreach (MasterdataService::getCoins() as $coin) {
            $tableName = $prefix . $coin . '_accounts';
            if (!$this->checkHasTable($tableName)) {
                $this->error('Table ' . $tableName.' is NOT exist');
                continue;
            }

            $account = DB::table($tableName)->where('id', $id)->first();
            if ($account) {
                $this->info(json_encode($account));
            } else {
                $this->error($coin . ' account is NOT found');
            }
        }
    }

    private function checkHasAirdropAccount($id)
    {
        foreach (Consts::AIRDROP_TABLES as $coin) {
            $tableName = "airdrop_{$coin}_accounts";
            if (!$this->checkHasTable($tableName)) {
                $this->error('Table ' . $tableName.' is NOT exist');
                continue;
            }

            $account = DB::table($tableName)->where('id', $id)->first();
            if ($account) {
                $this->info(json_encode($account));
            } else {
                $this->error($coin . ' account is NOT found');
            }
        }
    }

    private function checkHasTable($tableName)
    {
        return Schema::hasTable($tableName);
    }
}
