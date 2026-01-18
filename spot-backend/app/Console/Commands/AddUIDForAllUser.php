<?php

namespace App\Console\Commands;

use App\Consts;
use App\Jobs\AddUIDForUser;
use App\Models\User;
use Illuminate\Console\Command;

class AddUIDForAllUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:UID';

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
        $userIds = User::pluck('id');

        foreach ($userIds as $id) {
            AddUIDForUser::dispatch($id)->onQueue(Consts::QUEUE_BLOCKCHAIN);
        }

        echo "DONE";
    }
}
