<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call(OauthClientsTableSeeder::class);
        $this->call(UsersTableSeeder::class);
        // $this->call(OrdersTableSeeder::class);
        // $this->call(PricesTableSeeder::class);
        // $this->call(OrderTransactionTableSeeder::class);
        // $this->call(TransactionsTableSeeder::class);
        // $this->call(NoticeTableSeeder::class);
        $this->call(CoinMarketCapTicketsTableSeeder::class);
        $this->call(AdminsTableSeeder::class);
        $this->call(AdminBankAccountsTableSeeder::class);
        $this->call(AmlSettingSeeder::class);
        $this->call(AmlTransactionSeeder::class);
        $this->call(NewsUserTableSeeder::class);
        $this->call(ReferrerSettingTableSeeder::class);

//        $this->call(MarginInstrumentSeeder::class);
        $this->call(ReferrerSettingTableSeeder::class);
//        $this->call(MarginInsuranceUserSeeder::class);
//        $this->call(MarginSeeder::class);
        $this->call(MarketFeeSettingSeeder::class);
        $this->call(PromotionCategorySeeder::class);

        $this->call(ReferralReportRankingSeeder::class);
        $this->call(ReferrerClientLevelSeeder::class);
    }
}
