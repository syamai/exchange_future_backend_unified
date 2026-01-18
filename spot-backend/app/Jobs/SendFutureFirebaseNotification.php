<?php

namespace App\Jobs;

use App\Consts;
use App\Utils;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendFutureFirebaseNotification implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public $message;

	/**
	 * Create a new job instance.
	 *
	 * @return void
	 */
	public function __construct($message)
	{
        $this->queue = Consts::QUEUE_FUTURE_FIREBASE_NOTIFICATION;
		$this->message = $message;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle()
	{
		if (env('SEND_FUTURE_FIREBASE_NOTIFICATION', true)) {
            Utils::kafkaProducer(Consts::TOPIC_CONSUMER_FIREBASE_NOTIFICATION_FUTURE, $this->message);
		}
	}
}

