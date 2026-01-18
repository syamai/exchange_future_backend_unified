<?php

namespace App\Console\Commands;

use App\Consts;
use App\Models\MultiReferrerDetails;
use App\Models\User;
use Illuminate\Console\Command;

class UpdateReferrerRecord extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:referrer-record';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'update user missing referrer record';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        // get all id user
        $allUserId = User::query()->where([
            ['status', Consts::USER_ACTIVE],
            ['type', Consts::USER_TYPE_NORMAL]
        ])->pluck('id')->toArray();

        // get user had record referrer
        $userHaveRecordRef = User::query()->join('referrer_multi_level_details', 'users.id', '=',
            'referrer_multi_level_details.user_id')
            ->where([
                ['status', Consts::USER_ACTIVE],
                ['type', Consts::USER_TYPE_NORMAL]
            ])->pluck('users.id')->toArray();

        $userIds = User::query()->whereIn('id', $allUserId)->whereNotIn('id', $userHaveRecordRef)->pluck('id');
        $lstId = array();
        foreach ($userIds as $userId) {
            $lstId[] = [
                'user_id' => $userId
            ];
        }
        logger()->info("LIST USER ID MISSING RECORD REFERRER========= " . json_encode($lstId));

        MultiReferrerDetails::query()->insert($lstId);
    }
}
