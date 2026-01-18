<?php

namespace App\Jobs;

use App\Http\Services\MasterdataService;
use App\Models\Order;
use App\Models\SpotCommands;
use App\Consts;
use App\Utils;
use App\Utils\BigNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Exception;

class SendTradeFeeToME extends RedisQueueJob
{

    private $data = null;

    /**
     * Create a new job instance.
     * @param $data
     */
    public function __construct($data)
    {
        $data = json_decode($data, true);
        $this->data = $data;
        if ($data[0]) {
            $this->data = $data[0];
        }
    }

    public static function getUniqueKey()
    {
        $key = md5(serialize(func_get_args()));
        return static::getQueueName().'_'.$key;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            if ($this->data) {
                //send kafka ME
                logger()->info('Send Trade Fee to ME Request ==============' . json_encode($this->data));

                $payload = json_encode($this->data);
                $commandKey = md5($payload);
                $command = SpotCommands::on('master')->where('command_key', $commandKey)->first();

                if(!$command) {
                    $command = SpotCommands::create([
                        'command_key' => $commandKey,
                        'type_name' => $this->data['type'],
                        'user_id' => $this->data['data']['userId'],
                        'obj_id' => $this->data['data']['tradeId'],
                        'payload' => $payload

                    ]);
                    if (!$command) {
                        throw new Exception('can not create command');
                    }
                    $this->data['data']['commandId'] = $command->id;
                    Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_COMMAND, $this->data);
                }
            }

        } catch (\Exception $e) {
            logger()->error("REQUEST TRADE FEE TO ME FAIL ======== " . $e->getMessage());
            throw $e;
        }
    }
}
