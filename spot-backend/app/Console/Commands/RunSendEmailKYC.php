<?php

namespace App\Console\Commands;

use App\Consts;
use App\Mail\KycPrompt;
use App\Models\Price;
use App\Models\SpotCommands;
use App\Models\TmpPrice;
use App\Models\TotalPrice;
use App\Models\User;
use App\Utils;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class RunSendEmailKYC extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:notify-email-kyc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command send email complete kyc today';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
    	$users = User::with(['userSamsubKYC'])
			->whereNotNull('registered_at')
			->where('registered_at', '<', Carbon::now()->subDay())
			->where('is_send_mail', 0)
			->where(['users.status' => Consts::USER_ACTIVE])
			->get();


    	foreach ($users as $user) {
    		if (!$user->userSamsubKYC || $user->userSamsubKYC->status != Consts::KYC_STATUS_VERIFIED) {
    			//send email
				Mail::queue(new KycPrompt($user->email));
				$user->is_send_mail = 1;
				$user->save();
			}
		}

    }
}
