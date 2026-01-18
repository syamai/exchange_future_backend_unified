<?php

namespace App\Console\Commands;

use App\Consts;
use App\Jobs\SendDataToFutureEvent;
use App\Jobs\SendDataToServiceEvent;
use App\Models\User;
use Illuminate\Console\Command;

class SyncAccountToFutureEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'future-event:sync-account {id} {--confirm=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command sync account to future event';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (!env('SEND_DATA_TO_FUTURE_EVENTS', false)) {
            $this->info('EVN SEND_DATA_TO_FUTURE_EVENTS DISABLE');
            return;
        }

        $id = $this->argument('id');
        if ($id == 'all') {
            if ($this->option('confirm') === 'yes' || $this->confirm("Do you want to continue?")) {
                User::where('type', Consts::USER_TYPE_NORMAL)
					->where('status', Consts::USER_ACTIVE)
					->chunkById(100, function ($members) {
						foreach ($members as $member) {
							$this->sendData($member);
						}
					});
            }
            return;
        }
        $user = User::find($id);
        if (!$user) {
            $this->info('User not found');
            return;
        }

        if ($this->option('confirm') === 'yes' || $this->confirm("Do you want to continue?")) {
            $this->sendData($user);
        }

    }

    private function sendData($user)
    {
		SendDataToFutureEvent::dispatch('register', $user->id);
    }
}
