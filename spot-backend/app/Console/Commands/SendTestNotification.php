<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Notifications\TestNotification;

class SendTestNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send test notification via socket';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        echo "sending test notification\n";
        User::find(1)->notify(new TestNotification());
    }
}
