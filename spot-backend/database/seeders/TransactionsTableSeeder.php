<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class TransactionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        DB::table('transactions')->truncate();
        $coins = [
            'usd',
            'btc',
            'eth',
            'amal',
            'bch',
            'ltc',
            'xrp',
            'eos',
            'ada',
            'usdt'
        ];

        foreach (range(1, 50) as $index) {
            $transactionDates[] = Faker\Factory::create()->dateTimeBetween('-4 months', '+4 months');
        }

        for ($i = 0; $i < 500; $i ++) {
            foreach ($coins as $typeCoin) {
                $this->createDeposit($typeCoin, $transactionDates);
                $this->createWithdraw($typeCoin, $transactionDates);
            }
        }
    }

    private function createDeposit($typeCoin, $transactionDates)
    {
        $transaction = new \Transaction\Models\Transaction();
        $transaction->user_id = 1;
        $transaction->transaction_id = Uuid::uuid4();
        $transaction->currency = $typeCoin;
        $transaction->amount = rand(1, 100) + rand(1, 1000) / 1000;
        $transaction->status = 'success';
        $transaction->transaction_date = $transactionDates[array_rand($transactionDates, 1)];
        $transaction->created_at = App\Utils::currentMilliseconds();
        $transaction->updated_at = App\Utils::currentMilliseconds();
        $transaction->save();
    }

    private function createWithdraw($typeCoin, $transactionDates)
    {
        $transaction = new \Transaction\Models\Transaction();
        $transaction->user_id = 1;
        $transaction->transaction_id = Uuid::uuid4();
        if ($typeCoin == 'usd') {
            $transaction->foreign_bank_account = 'KB국민은행 857*******5678';
            $transaction->foreign_bank_account_holder = '홍길동';
        }
        $transaction->currency = $typeCoin;
        $transaction->amount = (rand(1, 100) + rand(1, 1000) / 1000) * -1;
        $transaction->fee = rand(1, 10) + rand(1, 100) / 100;
        $transaction->status = 'success';
        $transaction->transaction_date = $transactionDates[array_rand($transactionDates, 1)];
        $transaction->created_at = App\Utils::currentMilliseconds();
        $transaction->updated_at = App\Utils::currentMilliseconds();

        $transaction->save();
    }
}
