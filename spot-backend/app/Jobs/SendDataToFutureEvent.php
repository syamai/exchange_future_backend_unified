<?php

namespace App\Jobs;

use App\Consts;
use App\Models\User;
use App\Models\UserDeviceRegister;
use App\Utils;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Exception;
use Log;

class SendDataToFutureEvent implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public $userId;
	public $type;

	/**
	 * Create a new job instance.
	 *
	 * @return void
	 */
	public function __construct($type, $userId)
	{
		$this->queue = Consts::QUEUE_ACCOUNT_FUTURE_EVENTS;
		$this->userId = $userId;
		$this->type = $type;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle()
	{
		if (env('SEND_DATA_TO_FUTURE_EVENTS', false)) {

			$date = Carbon::now()->format('Y-m-d');
			$channel = Log::build([
				'driver' => 'single',
				'path' => storage_path("logs/future-events/{$date}.log"),
			]);

			$data = [
				"userId" => $this->userId
			];
			$topic = "";
			if ($this->type == 'register') {
                $topic = Consts::TOPIC_FUTURE_EVENT_USER_REGISTER;
				$user = User::find($this->userId);
				if (!$user) {
					Log::stack([$channel])->info("Error not get info user: {$this->type} - {$this->userId}");
					return;
				}

				$kyc = $user->userSamsubKYC && $user->userSamsubKYC->status == Consts::KYC_STATUS_VERIFIED;
				$hasLoggedIn = $user->last_login_at ? true : false;
				$referrerId = $user->referrerUser ? $user->referrerUser->id : null;
				$data = [
				    'id' => $user->id,
                    "email"=> $user->email,
					'uid' => $user->uid,
                    //"referralId" => $referrerId,
					"kycStatus" => $kyc,
					"hasLoggedIn" => $hasLoggedIn
				];

				if ($referrerId) {
                    $data['referralId'] = $referrerId;
                }

			} elseif ($this->type == 'kyc') {
				$topic = Consts::TOPIC_FUTURE_EVENT_USER_KYC;
            } elseif ($this->type == 'login') {
                $topic = Consts::TOPIC_FUTURE_EVENT_USER_FIRST_LOGIN;
			}

			if (!$topic) {
				Log::stack([$channel])->info("Error Topic: {$this->type} - {$this->userId} ({$topic})");
				return;
			}

			//send kafka to future
			try {
                Utils::kafkaProducer($topic, $data);
				Log::stack([$channel])->info("Success: {$this->type} - {$this->userId} - " . json_encode($data));
			} catch (\Exception $exception) {
				Log::stack([$channel])->info("Error: {$this->type} - {$this->userId} - ". json_encode($data) . ' - ' . $exception->getMessage());
				throw $exception;

			}

		}
	}
}

