<?php

namespace App\Jobs;

use App\Consts;
use App\Models\User;
use App\Models\UserDeviceRegister;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Exception;
use Log;

class SendDataToServiceEvent implements ShouldQueue
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
		$this->queue = Consts::QUEUE_ACCOUNT_EVENTS;
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
		if (env('SEND_DATA_TO_SERVICE_EVENTS', false)) {

			$date = Carbon::now()->format('Y-m-d');
			$channel = Log::build([
				'driver' => 'single',
				'path' => storage_path("logs/events/{$date}.log"),
			]);

			$baseUrlEvent = env('API_SERVICE_EVENTS', '');
			$path = '';

			$data = [
				"user_id" => "{$this->userId}"
			];
			if ($this->type == 'register') {
				$path = '/members';
				$user = User::find($this->userId);
				if (!$user) {
					Log::stack([$channel])->info("Error not get info user: {$this->type} - {$this->userId}");
					return;
				}

				$device = UserDeviceRegister::where(['user_id' => $user->id, 'state' => 'connectable'])
					->first();


				/*if (!$device) {
					Log::stack([$channel])->info("Error not get info device: {$this->type} - {$this->userId}");
					return;
				}*/
				$signUpIp = '';
				$deviceName = '';

				if ($device) {
					$signUpIp = $device->latest_ip_address;
					$deviceName = "{$device->platform} ({$device->operating_system})";
				}

				$kyc = $user->userSamsubKYC && $user->userSamsubKYC->status == Consts::KYC_STATUS_VERIFIED;
				$kycDate = $kyc ? $user->userSamsubKYC->updated_at : null;
				$referrerId = $user->referrerUser ? $user->referrerUser->id : '';

				$data = array_merge($data, [
					'uid' => $user->uid,
					"email"=> $user->email,
					"is_kyc" => $kyc,
					"referral_id" => "{$referrerId}",
					"referral_code" => $user->referrer_code,
					"ip_registered" => $signUpIp,
					"device" => $deviceName,
                    "createdAt" => $user->created_at,
                    'kycDate' => $kycDate
				]);

			} elseif ($this->type == 'kyc') {
				$path = '/members/kyc';
			}

			$url = $baseUrlEvent . $path;
			$userConfig = env('EVENTS_USER');
			$passConfig = env('EVENTS_PASSWORD');
			$token = 'Basic ' . base64_encode("$userConfig:$passConfig");

			if (!$baseUrlEvent || !$path) {
				Log::stack([$channel])->info("Error path: {$this->type} - {$this->userId} ({$baseUrlEvent}{$path})");
				return;
			}

			//send api
			try {
				$client = new Client();
				$response = $client->request('POST', $url, [
					'headers' => [
						'Content-Type' => 'application/json',
						'Authorization' => $token
					],
					'json' => $data,
					'timeout' => 30,
					'connect_timeout' => 30,
				]);

				if ($response->getStatusCode() >= 400) {
					Log::stack([$channel])->info("Error: {$this->type} - {$this->userId} - ". json_encode($data) . ' - ' . json_encode($response->getBody()));
					return;
				}

				$dataResponse = json_encode($response->getBody()->getContents());
				Log::stack([$channel])->info("Success: {$this->type} - {$this->userId} - " . json_encode($data) . " - "." ({$dataResponse})");
			} catch (\Exception $exception) {
				Log::stack([$channel])->info("Error: {$this->type} - {$this->userId} - ". json_encode($data) . ' - ' . $exception->getMessage());
				throw $exception;

			}

		}
	}
}

