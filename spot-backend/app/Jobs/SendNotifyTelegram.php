<?php

namespace App\Jobs;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Exception;
use Log;

class SendNotifyTelegram implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public $message;
	public $type;

	/**
	 * Create a new job instance.
	 *
	 * @return void
	 */
	public function __construct($type, $message)
	{
		$this->message = $message;
		$this->type = $type;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle()
	{
		if (env('TELEGRAM_SEND_NOTIFY', false)) {

			$date = Carbon::now()->format('Y-m-d');
			$channel = Log::build([
				'driver' => 'single',
				'path' => storage_path("logs/send-telegram/{$date}.log"),
			]);

			Log::stack([$channel])->info("BEGIN");
			$botToken = env('TELEGRAM_BOT_TOKEN_'.strtoupper($this->type), '');
			$chatId = env('TELEGRAM_CHAT_ID', '');
			$theadId = env('TELEGRAM_MESSAGE_THEAD_ID_'.strtoupper($this->type));


			if ($chatId && $botToken && $theadId) {

				$dateTime = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s');
				try {
					$data = [
						'chat_id' => $chatId,
						'message_thread_id' => $theadId,
						'text' => $this->message ." ({$dateTime})",
					];
					$telegramUrl = config('telegram.prefix_url') . "/bot{$botToken}/sendMessage";
					$client = new Client();
					$client->request('GET', $telegramUrl, [
						'query' => $data,
						'timeout' => 5,
						'connect_timeout' => 5,
					]);
				} catch (Exception $e) {
					Log::stack([$channel])->info("Error: {$this->type} - {$this->message} ({$e->getMessage()})");
				}
			} else {
				Log::stack([$channel])->info("Info Error: {$this->type} - {$this->message} ({$botToken} :: {$chatId} :: {$theadId})");
			}
		}
	}
}

