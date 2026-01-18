<?php

namespace App\Console\Commands;

use App\Consts;
use App\Models\KrwSetting;
use App\Utils;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;
use App\Utils\BigNumber;

class GetExchangeRateKRW extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_exchange_rate_krw:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Exchange Rate KRW - USDT to save database';



    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle()
    {

        $client = new Client();

        $url = "https://gw.bithumb.com/exchange/v1/comn/exrate";

        try {
            $time = Utils::currentMilliseconds();
            $res = $client->get($url, [
                'query' => [
                    '_' => $time,
                    'retry' => 0
                ],
                'timeout' => 5,
                'connect_timeout' => 5,
            ]);
            $dataRes = json_decode($res->getBody(), true);
            if (!empty($dataRes['data']['currencyRateList'])) {
                foreach ($dataRes['data']['currencyRateList'] as $v) {
                    if ($v['currency'] == 'USD') {
                        $rate = $v['rate'];//BigNumber::round(BigNumber::new($v['rate']), BigNumber::ROUND_MODE_HALF_UP, Consts::DIGITS_NUMBER_PRECISION_2);
                        if ($rate > 0) {
                            KrwSetting::updateOrCreate(
                                ['key' => 'exchange_rate'],
                                ['key' => 'exchange_rate', 'value' => $rate]
                            );
                        }
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            logger()->error("REQUEST GET EXCHANGE RATE KRW - USDT FAIL ======== " . $e->getMessage());
            Log::error($e);
        }
    }
}
