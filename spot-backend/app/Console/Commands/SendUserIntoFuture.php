<?php

namespace App\Console\Commands;

use App\Consts;
use App\Jobs\SendUserFuture;
use App\Models\User;
use Illuminate\Console\Command;

class SendUserIntoFuture extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:save_user_future';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Save users spot into future';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $users = User::where('type', '<>', Consts::USER_TYPE_BOT)->get();
        foreach ($users as $user) {
            $data = [
                'id' => $user->id,
                'email' => $user->email,
                'position' => $user->type,
                'role' => 'USER',
                'isLocked' => 'UNLOCKED',
                'status' => strtoupper($user->status)
            ];
            SendUserFuture::dispatch($data)->onQueue(Consts::QUEUE_BLOCKCHAIN);
        }

        return true;
    }
}
