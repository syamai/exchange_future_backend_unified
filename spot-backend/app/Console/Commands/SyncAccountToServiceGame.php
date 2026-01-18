<?php

namespace App\Console\Commands;

use App\Consts;
use App\Jobs\SendDataToServiceGame;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncAccountToServiceGame extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:sync-account {id} {--confirm=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
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

        if ($user->status != Consts::USER_ACTIVE) {
            $this->info('User not active');
            return;
        }

        if ($this->option('confirm') === 'yes' || $this->confirm("Do you want to continue?")) {
            $this->sendData($user);
        }

    }

    private function sendData($user)
    {
        SendDataToServiceGame::dispatch('register', $user->id);
    }
}
