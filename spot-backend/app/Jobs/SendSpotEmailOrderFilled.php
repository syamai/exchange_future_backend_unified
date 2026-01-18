<?php

namespace App\Jobs;

use App\Consts;
use App\Mail\SpotOrderFilled;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class SendSpotEmailOrderFilled extends RedisQueueJob
{

	private $orderId;
	private $email;

	/**
	 * Create a new job instance.
	 *
	 * @return void
	 */
	public function __construct($json)
	{
		$data = json_decode($json);
		$this->orderId = $data[0];
	}

	/*protected static function getNextRun()
	{
		return static::currentMilliseconds() + 300;
	}*/

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle()
	{
		$date = Carbon::now()->format('Y-m-d');
		$channel = Log::build([
			'driver' => 'single',
			'path' => storage_path("logs/mail-spot-trade/{$date}.log"),
		]);
		try {
			$order = Order::on('master')->find($this->orderId);
			if ($order) {
				if ($order->type != Consts::ORDER_TYPE_MARKET && $order->status == Consts::ORDER_STATUS_EXECUTED) {
					// send email notify to user
					$user = User::find($order->user_id);
					if (!$user) {
						throw new Exception('Cannot get info user:'.$order->user_id);
					}
					Mail::queue(new SpotOrderFilled($user, $order));
					Log::stack([$channel])->info("Send email: {$this->orderId} - {$order->type} (userId: {$order->user_id})");

				}
			} else {
				throw new Exception('Cannot get info order:'.$this->orderId);
			}
		} catch (Exception $e) {
			Log::stack([$channel])->info("Error email: {$this->orderId} - {$e->getMessage()}");
			Log::error('Process Send Spot Email Trade. Failed order: ' . $this->orderId);
			Log::error($e);
			//throw $e;
		}
	}
}
