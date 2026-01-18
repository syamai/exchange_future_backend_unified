<?php

namespace App\Console\Commands;

use App\Events\AdminNotificationUpdated;
use App\Models\Admin;
use App\Notifications\TestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class TestSendAdminNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin_notification:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send test admin notification via socket';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        echo "sending test admin notification\n";
        Notification::send(Admin::all(), new TestNotification());
    }
}
