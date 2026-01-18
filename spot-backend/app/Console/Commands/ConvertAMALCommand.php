<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Consts;
use App\Models\AmalAccount;
use App\Models\User;
use App\Models\AirdropAmalAccount;
use App\Models\AirdropHistoryLockBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConvertAMALCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:convert_amal_to_lock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert AMAL from buying salepoint to Dividend Balance';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $records = AmalAccount::get();
        foreach ($records as $record) {
            if ($record->available_balance > 0) {
                $this->createHistoryLockAirdropRecord($record->id, $record->available_balance);
                $this->updateAMALBalance($record->id);
            }
        }
    }

    public function createHistoryLockAirdropRecord($userId, $totalBalance)
    {
        $this->updateLockBalanceAccount($userId, $totalBalance);
        $user = User::find($userId);
        $data = [
            'user_id' => $userId,
            'email' => $user->email,
            'status' => Consts::AIRDROP_UNLOCKING,
            'total_balance' => $totalBalance,
            'amount' => 0,
            'unlocked_balance' => 0,
            'last_unlocked_date' => Carbon::now()->toDateString(),
            'type' => Consts::AIRDROP_TYPE_SPECIAL,
        ];
        return AirdropHistoryLockBalance::create($data);
    }

    public function updateAMALBalance($recordId)
    {
        AmalAccount::where('id', $recordId)->update([
            'balance' => 0,
            'available_balance' => 0,
            'usd_amount' => 0
        ]);
    }

    public function updateLockBalanceAccount($userId, $amount)
    {
        return AirdropAmalAccount::where('id', $userId)
            ->update([
                'balance' => DB::raw('balance + ' . $amount),
            ]);
    }
}
