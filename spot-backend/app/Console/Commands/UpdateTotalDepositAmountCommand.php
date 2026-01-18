<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Consts;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Transaction\Models\Transaction;
use App\Jobs\UpdateTotalVolumeDepositJob;
use App\Utils\BigNumber;
use Illuminate\Support\Facades\Cache;

class UpdateTotalDepositAmountCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'total_deposit_amout:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate total deposit amount for each user at first, before running command.';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //NOTE: uncomment for initialing Deposite Amount to calculate trading-volume only
        //running manually by command: php artisan total_deposit_amout:update

        // $records = Transaction::get();
        // foreach($records as $record) {
        //     if((!$this->isBot($record->user_id)) && (!$this->isBonus($record->from_address)) && ($this->isDeposit($record->amount))) {
        //         $updateTotalVolumeDepositJob = new UpdateTotalVolumeDepositJob($record);
        //         $updateTotalVolumeDepositJob->handle();
        //     }
        // }
    }

    public function isBonus($type)
    {
        if (!$type) {
            return true;
        }
        if (in_array($type, Consts::DEPOSIT_BONUS)) {
            return true;
        }
        return false;
    }

    public function isDeposit($amount)
    {
        return (BigNumber::new($amount)->comp(0) > 0);
    }

    public function isBot($userId)
    {
        $bots = [];
        if (Cache::has('listBots')) {
            $bots = Cache::get('listBots');
        }
        if (in_array($userId, $bots)) {
            return true;
        }
        $user = User::where('id', $userId)->first();
        if (!$user) {
            return true;
        }
        if ($user->type == 'bot') {
            array_push($bots, $userId);
            Cache::put('listBots', $bots);
            return true;
        }
        return false;
    }
}
