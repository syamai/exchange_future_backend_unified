<?php

namespace App\Console\Commands;

use App\Consts;
use App\Jobs\CreateUserAccounts;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class FixAccountAllCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user_account:fix-all {--confirm=}';

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
            CreateUserAccounts::dispatch($id)->onQueue(Consts::QUEUE_BLOCKCHAIN);
        }

        echo "DONE";
    }
}
