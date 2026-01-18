<?php

namespace App\Console\Commands;

use App\Consts;
use App\Models\User;
use App\Http\Services\MasterdataService;
use App\Jobs\CreateUserAccounts;
use App\Utils;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncFutureUserAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user_account:sync {id} {--confirm=}';

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
                User::chunkById(100, function ($members) {
                    foreach ($members as $member) {
                        $this->sendFutureKafka($member);
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
            $this->sendFutureKafka($user);
        }


    }

    private function sendFutureKafka($user)
    {
        $data = [
            'id' => $user->id,
            'email' => $user->email,
            'role' => 'USER',
            'status' => strtoupper($user->status),
            'uid' => $user->uid,
			'isBot' => $user->type == Consts::USER_TYPE_BOT
        ];
        $topic = Consts::TOPIC_PRODUCER_SYNC_USER;

        Utils::kafkaProducer($topic, $data);
    }
}
