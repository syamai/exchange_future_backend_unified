<?php

namespace App\Console\Commands;

use App\Models\UserDeviceRegister;
use Illuminate\Console\Command;
use App\Mail\LoginNewIP;
use Illuminate\Support\Facades\Mail;

class SendEmailLoginNewIP extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'login_newIP:confirm {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email confirm login new IP success';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $userId = $this->argument('id');
        $device = UserDeviceRegister::where(['user_id' => $userId])->orderByDesc('id')->first();
        if ($device) {
            Mail::queue(new LoginNewIP($device));
            $this->info('Sent mail successfully!');
        }


    }
}
