<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\UserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConvertAMALToDividend extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transfer:from_spot_and_airdrop_to_lock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transfer AMAL in available from Spot and Airdrop Balance to lock';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    private $userService;

    public function __construct()
    {
        parent::__construct();
        $this->userService = new UserService();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->transferFromSpotAMALToLock();
        $this->transferFromAirdropAvailableBalanceToLock();
    }

    public function transferFromSpotAMALToLock()
    {
        $users = DB::table('spot_amal_accounts')->where('available_balance', '>', 0)
            ->get();
        foreach ($users as $user) {
            if ($user->available_balance == $user->balance) {
                $amount = $user->available_balance;
                //Convert From Spot To Main
                DB::beginTransaction();
                try {
                    $this->userService->transferBalance($amount, Consts::CURRENCY_AMAL, Consts::TYPE_EXCHANGE_BALANCE, Consts::TYPE_MAIN_BALANCE, $user->id);
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error($e);
                }

                //Transfer From Main To Lock
                DB::beginTransaction();
                try {
                    $this->userService->transferBalanceFromMainToAirdrop($amount, Consts::CURRENCY_AMAL, Consts::TYPE_MAIN_BALANCE, Consts::TYPE_AIRDROP_BALANCE, $user->id, Consts::AIRDROP_TYPE_SPECIAL);
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error($e);
                }
            }
        }
    }

    public function transferFromAirdropAvailableBalanceToLock()
    {
        $users = DB::table('airdrop_amal_accounts')->where('available_balance', '>', 0)
            ->get();
        foreach ($users as $user) {
            $amount = $user->available_balance;
            //Convert From Airdrop Available To Main
            DB::beginTransaction();
            try {
                $this->userService->transferBalance($amount, Consts::CURRENCY_AMAL, Consts::TYPE_AIRDROP_BALANCE, Consts::TYPE_MAIN_BALANCE, $user->id);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error($e);
            }

            //Transfer From Main To Lock
            DB::beginTransaction();
            try {
                $this->userService->transferBalanceFromMainToAirdrop($amount, Consts::CURRENCY_AMAL, Consts::TYPE_MAIN_BALANCE, Consts::TYPE_AIRDROP_BALANCE, $user->id, Consts::AIRDROP_TYPE_SPECIAL);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error($e);
            }
        }
    }
}
