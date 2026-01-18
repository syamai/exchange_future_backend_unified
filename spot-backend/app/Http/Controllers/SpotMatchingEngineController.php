<?php

namespace App\Http\Controllers;

use App\Models\Process;
use App\Models\User;
use App\Notifications\RegisterCompletedNotification;
use App\Notifications\WithdrawalCanceledNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Transaction\Models\Transaction;
use Log;

class SpotMatchingEngineController extends AppBaseController
{
    public function sendInit(Request $request)
    {
    	$time = $request->time ?? '';
    	if ($time) {
    		$timeCheck = Carbon::now()->format("mdHi");//->toDateTimeString("mmddHHii");
			if ($timeCheck == $time) {
				$date = Carbon::now()->format('Y-m-d');
				$channel = Log::build([
					'driver' => 'single',
					'path' => storage_path("logs/send-init/{$date}.log"),
				]);

				Log::stack([$channel])->info("BEGIN");
				$process = Process::on('master')->firstOrCreate(['key' => 'spot_send_init_kafka_matching_engine']);
				if ($process->processed_id == 0) {
					$process->processed_id = 1;
					$process->save();
					Artisan::call('kafka_me:init');
					Log::stack([$channel])->info('Send');
					return ['status' => true, 'Init done'];

				}
				return ['status' => false, 'Init running'];
			} else {
				return ['status' => false, 'Time not match'];
			}

		}
		return ['status' => false, 'not data Time'];

    }

    public function test(Request $request)
	{
		$userId = $request->uid ?? 534;
		$transId = $request->id ?? 192;
		$user = User::find($userId);
		/*$transaction = Transaction::lockForUpdate()->where('id', $transId)->filterWithdraw()->first();

		if ($transaction && $user) {
			$user->notify(new WithdrawalCanceledNotification($transaction));
			return ['status' => true, 'Done'];
		}*/

		if ($user) {
			$user->notify(new RegisterCompletedNotification($user));
			return ['status' => true, 'Done'];
		}

		return ['status' => false, 'Fail'];

	}
}
