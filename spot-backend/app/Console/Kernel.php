<?php

namespace App\Console;

use App\Enums\TypeVoucher;
use App\Providers\TelescopeServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Carbon\Carbon;
use App\Http\Services\AirdropService;
use Illuminate\Support\Facades\Schema;
use App\Consts;
use App\Models\AirdropSetting;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();
        // $schedule->command('coin_market_cap_ticker:update')->everyMinute();
//        $schedule->command('margin:indicator')->everyMinute();
        $schedule->command('coin_check_btc_eth_usd:crawl')->everyFifteenMinutes();
        // $schedule->command('calculate:user_fee_level')->dailyAt('01:00');
        // $schedule->command('calculate:withdrawal_limits')->dailyAt('00:30');
        $schedule->command('blockchain_address:create')->everyFiveMinutes();

        //$schedule->command('get_news_zendesk:update')->everyFiveMinutes();
        $schedule->command('check_expired_withdrawals:update')->hourly();
        //$schedule->command('trading_volume_record:create')->dailyAt('00:00');
        $schedule->command('vouchers:update_expired')->dailyAt('00:00');

        $schedule->command('price:tmp')->withoutOverlapping()->everyMinute();
        $schedule->command('market:top_gainer_loser')->withoutOverlapping()->everyFiveMinutes();
        $schedule->command('market:top_listing')->withoutOverlapping()->everyFiveMinutes();
        $schedule->command('market:top_volume')->withoutOverlapping()->everyFiveMinutes();
        $schedule->command('price:remove')->dailyAt('05:00');

        
        $schedule->command('get_exchange_rate_krw:update')->everyFiveMinutes();

        $schedule->command('run:notify-email-kyc')->withoutOverlapping()->everyFifteenMinutes();

        // Crawal price from coinmarketcap
        // $schedule->command('crawl:price')->hourly();

        // Airdrop
//        try {
//            $this->airdropSchedule($schedule);
//        } catch (\Exception $e) {
//            logger('AIRDROP-ERROR: ==========>');
//            logger()->error($e);
//        }

        // if ($this->app->environment('local')) {
            $schedule->command('telescope:prune')->daily();
        // }

        if (config('monitor.prometheus.enabled')) {
            $schedule->command('monitor:performance')->everyMinute();
            $schedule->command('monitor:cleanup')->hourly();
        }
        
        // Users overview: gains
        /*
        $schedule->command('spot:user-holdings')->withoutOverlapping()->everyMinute();
        $schedule->command('spot:snapshot_assets')->withoutOverlapping()->everyMinute();
        $schedule->command('overview:top-gains')->withoutOverlapping()->everyMinute();
        $schedule->command('overview:top-loses')->withoutOverlapping()->everyMinute();
        $schedule->command('cleanup:user_asset_snapshots')->withoutOverlapping()->dailyAt('03:00'); // chạy mỗi ngày lúc 3 giờ sáng
        */
        // Player Real Balance Report
        /*
        $schedule->command('spot:user-pendings')->withoutOverlapping()->everyMinute();
        $schedule->command('spot:user-transaction')->withoutOverlapping()->everyMinute();
        $schedule->command('spot:commission-rates')->withoutOverlapping()->everyMinute();
        $schedule->command('report:player-realbalance')->withoutOverlapping()->everyMinute();

        // set level referral client
        $schedule->command('affiliate:direct')->withoutOverlapping()->hourly();
        $schedule->command('affiliate:partner')->withoutOverlapping()->dailyAt('00:30');
        $schedule->command('referral:client')->withoutOverlapping()->hourly();
        $schedule->command('referral:set-level')
                ->monthlyOn(1, '00:10');
                
        //Ranking referrer clients
        $schedule->command('referral:client-report-daily')->withoutOverlapping()->dailyAt('00:15');
        $schedule->command('referral:ranking-weekly')
                 ->withoutOverlapping()
                 ->weeklyOn(1, '02:00');
        */
        

    }

    protected function airdropSchedule($schedule)
    {
        $nameTable = (new AirdropSetting)->getTableName();
        if (Schema::hasTable($nameTable)) {
            $setting = app(AirdropService::class)->getAirdropSetting();
            if ($setting) {
                $unlockTime = Carbon::createFromFormat('H:i', $setting->payout_time)->addMinute(config('airdrop.waiting_time_unlock'))->format('H:i');
                if ($setting->enable == Consts::AIRDROP_ENABLE) {
                    $schedule->command('calculate:amal_holding')->dailyAt($setting->payout_time);
                    $schedule->command('update:airdrop_amal_balance')->dailyAt($unlockTime);
                }
            }
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
