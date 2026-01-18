<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FakeUserBalance extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $userIds = User::pluck('id');
        foreach ($userIds as $userId) {
            $this->createUserUsdAccount($userId);
            $this->createUserAccount('btc_accounts', $userId);
            $this->createUserAccount('eth_accounts', $userId);
            $this->createUserAccount('amal_accounts', $userId);
            $this->createUserAccount('bch_accounts', $userId);
            $this->createUserAccount('ltc_accounts', $userId);
            $this->createXrpAccount('xrp_accounts', $userId);
            $this->createUserAccount('eos_accounts', $userId);
            $this->createUserAccount('ada_accounts', $userId);
            $this->createUserAccount('usdt_accounts', $userId);
        }
    }

    private function createUserUsdAccount($userId)
    {
        $balance = rand(100000000000, 500000000000);
        DB::table('usd_accounts')->where('id', $userId)->update([
            'balance' => $balance,
            'available_balance' => $balance,
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now()
        ]);
    }

    private function createUserAccount($table, $userId)
    {
        $balance = rand(1000, 10000) + rand(1, 1000) / 1000;
        DB::table($table)->where('id', $userId)->update([
            'balance' => $balance,
            'available_balance' => $balance,
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now()
        ]);
    }

    private function createXrpAccount($table, $userId)
    {
        $balance = rand(1000, 10000) + rand(1, 1000) / 1000;
        DB::table($table)->where('id', $userId)->update([
            'balance' => $balance,
            'available_balance' => $balance,
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now()
        ]);
    }
}
