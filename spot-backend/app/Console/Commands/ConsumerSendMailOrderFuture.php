<?php

namespace App\Console\Commands;

use App\Consts;
use App\Mail\FutureOrderFilled;
use App\Mail\FutureOrderLiquidated;
use App\Mail\FutureOrderStopLoss;
use App\Mail\FutureOrderTakeProfit;
use App\Models\User;
use App\Utils;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Junges\Kafka\Contracts\KafkaConsumerMessage;
use Exception;

class ConsumerSendMailOrderFuture extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consumer:send_mail_future';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'consumer send mail order future';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $topic = Consts::TOPIC_CONSUMER_SEND_MAIL_FUTURE;
        $handler = new HandlerSendMailOrderFuture();
        Utils::kafkaConsumer($topic, $handler);
    }
}

class HandlerSendMailOrderFuture
{
    public function __invoke(KafkaConsumerMessage $message)
    {
		$date = Carbon::now()->format('Y-m-d');
		$channel = Log::build([
			'driver' => 'single',
			'path' => storage_path("logs/mail-future-trade/{$date}.log"),
		]);

        $data = Utils::convertDataKafka($message);
        try {
        	$templateType = $data['templateType'];
        	if (isset($data['data'])) {
        		$userId = $data['data']['userId'] ?? 0;
        		$user = User::find($userId);
        		if ($user) {
        			//send email
					match($templateType) {
						'TEMPLATE_1' => Mail::queue(new FutureOrderFilled($user, $data['data'])),
						'TEMPLATE_2' => Mail::queue(new FutureOrderStopLoss($user, $data['data'])),
						'TEMPLATE_3' => Mail::queue(new FutureOrderTakeProfit($user, $data['data'])),
						'TEMPLATE_4' => Mail::queue(new FutureOrderLiquidated($user, $data['data'])),
					};
					Log::stack([$channel])->info("ConsumerSendMailOrderFuture. send Email: {$templateType}" . json_encode($data));
				} else {
					Log::stack([$channel])->error("ConsumerSendMailOrderFuture. Not get user: {$userId}" . json_encode($data));
				}
			} else {
				Log::stack([$channel])->error("ConsumerSendMailOrderFuture. Not get template: " . json_encode($data));
			}

        } catch (Exception $e) {
			Log::stack([$channel])->info("Error email: ".json_encode($data)." - {$e->getMessage()}");
            Log::error('ConsumerSendMailOrderFuture. Failed to send mail: ' . json_encode($data));
            Log::error($e);
            throw $e;
        }

    }
}
