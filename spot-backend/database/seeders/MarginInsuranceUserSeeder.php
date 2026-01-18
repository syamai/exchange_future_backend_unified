<?php

namespace Database\Seeders;

use App\Consts;
use App\Jobs\CreateUserAccounts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\MarginAccount;
use App\Models\AmalMarginAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarginInsuranceUserSeeder extends Seeder
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
//        $this->createInsuranceUser();
    }

    private function createInsuranceUser()
    {
        $user = $this->getInsuranceUser();
        if (!$user) {
            User::insert([
                'name' => "Insurance Fund",
                'email' => Consts::INSURANCE_FUND_EMAIL,
                'password' => '',
                'remember_token' => Str::random(10),
                'type' => 'bot',
                'status' => 'active'
            ]);
            $user = $this->getInsuranceUser();
        }
        $userId = $user->id;
        $this->createUserData($userId);

        $record = MarginAccount::where('owner_id', $userId)->first();
        if (!$record) {
            MarginAccount::insert([
                'manager_id' => $userId,
                'owner_id' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
            AmalMarginAccount::insert([
                'owner_id' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
        }
    }

    private function getInsuranceUser()
    {
        return User::where('email', Consts::INSURANCE_FUND_EMAIL)->first();
    }

    public function createUserData($userId)
    {
        CreateUserAccounts::dispatch($userId)->onQueue(Consts::QUEUE_BLOCKCHAIN);
        try {
            $this->createSecuritySettings($userId);
            $this->createOrderBookSettings($userId);
        } catch (\Exception $exception) {
        }
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
