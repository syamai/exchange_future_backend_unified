<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Consts;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateCoinAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coin_account:create {coin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create coin-accounts';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $prefixs = [
            'main' => '',
            'margin' => Consts::TYPE_MARGIN_BALANCE . '_',
            'spot' => Consts::TYPE_EXCHANGE_BALANCE . '_',
            //'mam' => Consts::TYPE_MAM_BALANCE . '_',
            'airdrop' => Consts::TYPE_AIRDROP_BALANCE . '_'
        ];
        $coin = $this->argument('coin');
        try {
            foreach ($prefixs as $key => $prefix) {
                if ($key != 'airdrop') {
                    $this->insertRecord($prefix, $coin);
                    continue;
                }
                // Airdrop
                if (in_array($coin, Consts::AIRDROP_TABLES)) {
                    $this->insertRecord($prefix, $coin);
                }
            }
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    private function insertRecord($prefix, $coin)
    {
        $record = DB::table("{$prefix}{$coin}_accounts")->where('id', User::first()->id)->exists();
        if (!$record) {
            $marginPrefix = Consts::TYPE_MARGIN_BALANCE . '_';
            if ($coin == Consts::CURRENCY_BTC && $prefix == $marginPrefix) {
                DB::insert("INSERT {$prefix}{$coin}_accounts (id, balance, usd_amount, available_balance, created_at, updated_at) SELECT  id, 0, 0, 0, ?, ? FROM users", [Carbon::now(), Carbon::now()]);
            }
            $this->info("{$prefix}{$coin}_accounts filled successfully!");
        } else {
            $this->info("{$prefix}{$coin}_accounts had data.");
        }
    }
}
