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

class SendDataToServiceGame implements ShouldQueue
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
		$this->queue = Consts::QUEUE_ACCOUNT_GAMES;
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
		if (env('SEND_DATA_TO_SERVICE_GAME', false)) {

			$date = Carbon::now()->format('Y-m-d');
			$channel = Log::build([
				'driver' => 'single',
				'path' => storage_path("logs/games/{$date}.log"),
			]);

			$baseUrlEvent = env('API_SERVICE_GAME', '');
			$path = '';

			$data = [
				"userId" => $this->userId
			];
			$user = User::find($this->userId);
			if (!$user) {
				Log::stack([$channel])->info("Error not get info user: {$this->type} - {$this->userId}");
				return;
			}

			if ($this->type == 'register') {
				$path = '/sync/bitruth/users';

				$deviceName = '';
				$signUpIp = '';

				$device = UserDeviceRegister::where(['user_id' => $user->id, 'state' => 'connectable'])
					->first();

				if ($device) {
					$signUpIp = $device->latest_ip_address;
					$deviceName = "{$device->platform} ({$device->operating_system})";
				}

				$kyc = $user->userSamsubKYC && $user->userSamsubKYC->status == Consts::KYC_STATUS_VERIFIED;
				$kycDate = $kyc ? $user->userSamsubKYC->updated_at : null;
				$referrerId = $user->referrerUser ? $user->referrerUser->id : null;
				$securitySetting = $user->securitySetting;

				$data = array_merge($data, [
					'uid' => $user->uid,
					'name' => $user->name,
					"email" => $user->email,
					'status' => $user->status,
					'referrerId' => $referrerId,
					'referrerCode' => $user->referrer_code,
					'type' => $user->type,
					'phoneNo' => $user->phone_no,
					'mobileCode' => $user->mobile_code,
					'phoneNumber' => $user->phone_number,
					'fakeName' => $user->fake_name,
					'isPartner' => $user->is_partner,
					"ipRegistered" => $signUpIp,
					"device" => $deviceName,
					'kycDate' => $kycDate,
					'securityLevel' => $user->security_level,
					"securitySetting" => [
						'emailVerified' => $securitySetting->email_verified,
						'mailRegisterCreatedAt' => $securitySetting->mail_register_created_at,
						'phoneVerified' => $securitySetting->phone_verified,
						'identityVerified' => $securitySetting->identity_verified,
						'otpVerified' => $securitySetting->otp_verified
					],
					"createdAt" => $user->created_at,
				]);

			} elseif ($this->type == 'kyc') {
				$path = '/sync/bitruth/kyc';
				$securitySetting = $user->securitySetting;
				$kycDate = $securitySetting->identity_verified && $user->userSamsubKYC->updated_at ? Carbon::now()->toISOString() : null;
				$data = array_merge($data, [
					'kycStatus' => $securitySetting->identity_verified,
					'kycDate' => $kycDate,
					'otpVerified' => $securitySetting->otp_verified,
					'securityLevel' => $user->security_level,
				]);
			} elseif ($this->type == 'phone') {
				$path = '/sync/bitruth/verify-phone';
				$securitySetting = $user->securitySetting;
				$data = array_merge($data, [
					'isVerifiedPhone' => $securitySetting->phone_verified,
					'phoneNo' => $user->phone_no,
					'mobileCode' => $user->mobile_code,
					'phoneNumber' => $user->phone_number,
					'securityLevel' => $user->security_level,
				]);
			}

			$url = $baseUrlEvent . $path;
			$userConfig = env('AUTH_GAME_USER');
			$passConfig = env('AUTH_GAME_PASSWORD');
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
					Log::stack([$channel])->info("Error: {$this->type} - {$this->userId} - " . json_encode($data) . ' - ' . json_encode($response->getBody()));
					return;
				}

				$dataResponse = json_encode($response->getBody()->getContents());
				Log::stack([$channel])->info("Success: {$this->type} - {$this->userId} - " . json_encode($data) . " - " . " ({$dataResponse})");
			} catch (\Exception $exception) {
				Log::stack([$channel])->info("Error: {$this->type} - {$this->userId} - " . json_encode($data) . ' - ' . $exception->getMessage());
				throw $exception;

			}

		}
	}
}

