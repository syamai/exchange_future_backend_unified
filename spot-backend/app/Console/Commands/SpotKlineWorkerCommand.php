<?php

namespace App\Console\Commands;

use App\Http\Services\MasterdataService;
use App\Models\Order;
use App\Models\Price;
use App\Models\Process;
use App\Models\User;
use App\Utils;
use App\Utils\BigNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;

class SpotKlineWorkerCommand extends BaseLogCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:kline_worker';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create kline spot';

    protected $process;

    protected int $batchSize = 1000;
    protected $sleepTime = 100;

    const INTERVALS = [
        "1m" => 60000,
        "3m" => 180000,
        "5m" => 300000,
        "15m" => 900000,
        "30m" => 1800000,
        "1h" => 3600000,
        "2h" => 7200000,
        "4h" => 14400000,
        "6h" => 21600000,
        "8h" => 28800000,
        "12h" => 43200000,
        "1d" => 86400000,
        "3d" => 259200000,
        //"5d" => 432000000,
        "1w" => 604800000,
        //"1M" => 2592000000
    ];




    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->process = Process::firstOrCreate(['key' => $this->getProcessKey()]);
        $this->doInitJob();

        while (true) {
            $this->doJob();
            usleep($this->sleepTime);
        }
    }

    protected function getProcessKey(): string
    {
        return 'spot_kline_prices';
    }

    protected function log($message)
    {
        logger($message);
        $this->info($message);
    }

    protected function doInitJob()
    {
        //check adn create table kline pair
        $currencyCoins = MasterdataService::getOneTable('coin_settings');
        foreach ($currencyCoins as $pair) {
            $tableName = $this->getKlineTableName($pair->currency, $pair->coin);
            if(!Schema::hasTable($tableName)) {
                $params = [$tableName];
                $sqlParams = implode(',', array_fill(0, sizeof($params), '?'));
                DB::connection('master')->update('CALL create_table_kline(' . $sqlParams . ')', $params);
            }
        }

    }

    protected function doJob()
    {
        $count = 0;
        $shouldContinue = true;

        while ($shouldContinue) {
            DB::transaction(function () use (&$shouldContinue, &$count) {
                $this->doJobProccess($shouldContinue,$count);
            });
        };
    }

    protected function getKlineTableName($currency, $coin) {
        return strtolower("klines_{$coin}_{$currency}");
    }

    protected function getNextRecords($process)
    {
        $query = Price::where('id', '>', $process->processed_id);
        return $query->orderBy('id', 'asc')->limit($this->batchSize)->get();
    }

    /**
     * @param int $count
     * @return array
     */
    protected function doJobProccess(&$shouldContinue, &$count)
    {
        $count++;

        $process = Process::lockForUpdate()->find($this->process->id);
        $records = $this->getNextRecords($process);
        foreach ($records as $record) {
            $tableName = $this->getKlineTableName($record->currency, $record->coin);

            foreach (self::INTERVALS as $interval => $intervalTime) {
                $time = intval(floor($record->created_at / $intervalTime)) * $intervalTime;
                $kline = DB::connection('master')->table($tableName)
					->where(['time' => $time])
					->where('interval', 'like binary', $interval)
					->first();
                if (!$kline) {
					$priceOpen = $record->price;
                	$klineOld = DB::connection('master')->table($tableName)
						->where('time', '<', $time)
						->where('interval', 'like binary', $interval)
						->orderByDesc('time')
						->first();
                	if ($klineOld) {
						$priceOpen = $klineOld->close;
					}

                    DB::table($tableName)->insert([
                        'time' => $time,
                        'interval' => $interval,
                        'opening_time' => $time,
                        'closing_time' => $record->created_at,
                        'open' => $priceOpen,
                        'close' => $record->price,
                        'high' => $record->price,
                        'low' => $record->price,
                        'volume' => $record->quantity,
                        'quote_volume' => $record->amount,
                        'trade_count_crawled' => $record->is_crawled ? 1 : 0,
                        'trade_count' => $record->is_crawled ? 0 : 1,
                    ]);
                } else {
                    $dataUpdate = [
                        'volume' => BigNumber::new($kline->volume)->add($record->quantity)->toString(),
                        'quote_volume' => BigNumber::new($kline->quote_volume)->add($record->amount)->toString()
                    ];
                    /*if ($record->created_at < $kline->opening_time) {
                        $dataUpdate['opening_time'] = $record->created_at;
                        $dataUpdate['open'] = $record->price;
                    }*/
                    if ($record->created_at > $kline->closing_time) {
                        $dataUpdate['closing_time'] = $record->created_at;
                        $dataUpdate['close'] = $record->price;
                    }

                    if (BigNumber::new($kline->high)->sub($record->price)->toString() < 0) {
                        $dataUpdate['high'] = $record->price;
                    }

                    if (BigNumber::new($kline->low)->sub($record->price)->toString() > 0) {
                        $dataUpdate['low'] = $record->price;
                    }
                    if ($record->is_crawled) {
                        $dataUpdate['trade_count_crawled'] = $kline->trade_count_crawled + 1;
                    } else {
                        $dataUpdate['trade_count'] = $kline->trade_count + 1;
                    }


                    DB::table($tableName)->where(['time' => $time, 'interval' => $interval])->update($dataUpdate);
                }
            }

            $process->processed_id = $record->id;
        }

        $process->save();
        $this->process = $process;

        usleep($this->sleepTime);
        $shouldContinue = count($records) === $this->batchSize;
    }


}
