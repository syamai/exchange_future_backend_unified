<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Consts;
use App\Models\AirdropHistoryLockBalance;

class ConvertAllToLockSpecial extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:convert_all_unlock_to_special_type';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Change type of unlock record which created when user transfer AMAL from Main To Dividend';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        return AirdropHistoryLockBalance::where('type', '!=', Consts::AIRDROP_TYPE_SPECIAL)
            ->where('status', '!=', Consts::AIRDROP_SUCCESS)
            ->update([
                'type' => Consts::AIRDROP_TYPE_SPECIAL
            ]);
    }
}
