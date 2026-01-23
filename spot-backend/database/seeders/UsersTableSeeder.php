<?php

namespace Database\Seeders;

use App\Consts;
use App\Jobs\CreateUserAccounts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Utils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UsersTableSeeder extends Seeder
{
    private $prices = [
        'usd' => 35000,
        'btc' => 9728000,
        'eth' => 969000,
        'amal' => 35000,
        'bch' => 1440000,
        'ltc' => 176100,
        'xrp' => 1174,
        'eos' => 35000,
        'ada' => 35000,
        'usdt' => 35000
    ];
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->truncate();
        DB::table('user_blockchain_addresses')->truncate();
        DB::table('user_security_settings')->truncate();
        DB::table('user_order_book_settings')->truncate();
        foreach (array_keys($this->prices) as $currency) {
            DB::table("{$currency}_accounts")->truncate();
            DB::table("spot_{$currency}_accounts")->truncate();
        }
//        DB::table("margin_accounts")->truncate();

        $this->createBots();
        \App\Models\User::factory()->count(1)->create()->each(function ($u) {
            $this->createUserData($u->id, $u->email);
        });

        \App\Models\User::factory()->count(1)->create()->each(function ($u) {
            $u->email = 'trading@gmail.com';
            $u->save();
        });

        \App\Models\User::factory()->count(10)->create()->each(function ($u) {
            $this->createUserData($u->id, $u->email);
        });
    }

    private function createBots()
    {
        $password = bcrypt('123123');
        $password2 = bcrypt('1231234');

        $botCount = 500;
        if (Utils::isTesting()) {
            $botCount = 10; // speed up
        }
        for ($i = 1; $i < $botCount + 1; $i++) {
            $email = "bot$i@gmail.com";
            DB::table('users')->insert([
                'id' => $i,
                'name' => "Bot $i",
                'email' => $email,
                'password' => $i < 10 ? $password : $password2,
                'remember_token' => Str::random(10),
                'type' => 'bot',
                'is_tester' => 'active',
                'status' => 'active'
            ]);
            $this->createUserData($i, $email);
        }
    }

    public function createUserData($userId, $email)
    {
        $this->createUserAccounts($userId, $email);
        try {
            $this->createSecuritySettings($userId);
            $this->createOrderBookSettings($userId);
            $this->createAccountProfileSettings($userId);
        } catch (\Exception $exception) {
        }
    }

    private function createAccountProfileSettings($userId)
    {
        DB::table('account_profile_settings')->insertOrIgnore([
            'user_id' => $userId,
            'spot_trade_allow' => 1,
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now()
        ]);
    }

    private function createUserAccounts($userId, $email)
    {
        if (Utils::isTesting()) {
            $this->createUserUsdAccount($userId, $email);

            foreach (array_keys($this->prices) as $coin) {
                if ($coin != Consts::CURRENCY_XRP || $coin != Consts::CURRENCY_USD) {
                    $this->createUserAccount($coin, $userId, $email);
                }
            }

            $this->createXrpAccount($userId, $email);
        } else {
            CreateUserAccounts::dispatch($userId)->onQueue(Consts::QUEUE_BLOCKCHAIN);
        }
    }

    private function createUserUsdAccount($userId, $email)
    {
        $coin = 'usd';
        $table = $coin . "_accounts";
        $balanceSpot = rand(100000000000, 500000000000);
        $balanceMargin = rand(100000000000, 500000000000);
        $balance = $balanceSpot + $balanceMargin;


        $price = array_key_exists($coin, $this->prices) ? $this->prices[$coin] : 1000;
        DB::table($table)->insert([
            'id' => $userId,
            'balance' => $balance,
            'available_balance' => $balance,
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now()
        ]);

        $tableSpot = "spot_" . $coin . "_accounts";
        $priceSpot = array_key_exists($coin, $this->prices) ? $this->prices[$coin] : 1000;
        DB::table($tableSpot)->insert([
            'id' => $userId,
            'balance' => $balanceSpot,
            'available_balance' => $balanceSpot,
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now()
        ]);

        DB::table('user_transactions')->insert([
            'user_id' => $userId,
            'email' => $email,
            'ending_balance' => $balance,
            'debit' => $balance,
            'currency' => $coin,
            'created_at' => Utils::currentMilliseconds(),
            'type' => Consts::USER_TRANSACTION_TYPE_TRANSFER,
            'transaction_id' => 0
        ]);
    }

    private function createUserAccount($coin, $userId, $email)
    {
        if ($coin == 'usd') {
            return;
        }
        if ($coin == 'xrp') {
            return;
        }
        $table = $coin . "_accounts";
        $balance = rand(100000, 1000000) + rand(1, 1000) / 1000;
        $balanceSpot = rand(100000, 1000000) + rand(1, 1000) / 1000;
        $balanceMargin = rand(100000, 1000000) + rand(1, 1000) / 1000;


        $price = array_key_exists($coin, $this->prices) ? $this->prices[$coin] : 1000;
        DB::table($table)->insert([
            'id' => $userId,
            'balance' => $balance,
            'usd_amount' => $balance * $price,
            'available_balance' => $balance,
            'blockchain_address' => null,
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now()
        ]);

        $tableSpot = "spot_" . $coin . "_accounts";
        $priceSpot = array_key_exists($coin, $this->prices) ? $this->prices[$coin] : 1000;
        DB::table($tableSpot)->insert([
            'id' => $userId,
            'balance' => $balanceSpot,
            'usd_amount' => $balanceSpot * $price,
            'available_balance' => $balanceSpot,
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now()
        ]);

        DB::table('user_transactions')->insert([
            'user_id' => $userId,
            'email' => $email,
            'ending_balance' => $balance,
            'debit' => $balance,
            'currency' => $coin,
            'created_at' => Utils::currentMilliseconds(),
            'type' => Consts::USER_TRANSACTION_TYPE_TRANSFER,
            'transaction_id' => 0
        ]);
    }

    private function createXrpAccount($userId, $email)
    {
        $balance = rand(1000, 10000) + rand(1, 1000) / 1000;
        DB::table('xrp_accounts')->insert([
            'id' => $userId,
            'balance' => $balance,
            'usd_amount' => $balance * $this->prices['xrp'],
            'available_balance' => $balance,
            'blockchain_address' => null,
            'blockchain_sub_address' => '',
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now()
        ]);

        DB::table('spot_xrp_accounts')->insert([
            'id' => $userId,
            'balance' => $balance,
            'usd_amount' => $balance * $this->prices['xrp'],
            'available_balance' => $balance,
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now()
        ]);

        DB::table('user_transactions')->insert([
            'user_id' => $userId,
            'email' => $email,
            'ending_balance' => $balance,
            'debit' => $balance,
            'currency' => 'xrp',
            'created_at' => Utils::currentMilliseconds(),
            'type' => Consts::USER_TRANSACTION_TYPE_TRANSFER,
            'transaction_id' => 0
        ]);
    }

    private function createSecuritySettings($userId)
    {
        DB::table('user_security_settings')->insert([
            'id' => $userId,
            'email_verified' => 1,
            'phone_verified' => 0,
            'identity_verified' => 0,
            'bank_account_verified' => 0,
            'otp_verified' => 0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }

    private function createOrderBookSettings($userId)
    {
        foreach (array_keys($this->prices) as $coin) {
            $data = [
                'user_id' => $userId,
                'currency' => 'usd',
                'coin' => $coin,
            ];
            $settings = array_merge(Consts::DEFAULT_ORDER_BOOK_SETTINGS, $data);
            DB::table('user_order_book_settings')->insert($settings);
        }
    }
}
