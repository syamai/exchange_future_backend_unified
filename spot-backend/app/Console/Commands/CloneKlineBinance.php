<?php

namespace App\Console\Commands;

use App\Consts;
use App\Models\User;
use App\Utils\BigNumber;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CloneKlineBinance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clone:kline-binance {currency} {coin} {from?} {to?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */

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

    public function handle()
    {
		$currency = $this->argument('currency') ?? '';
		$coin = $this->argument('coin') ?? '';
		if (!$currency || !$coin) {
			return $this->info("COIN, CURRENCY is required");
		}

		$tableName = $this->getKlineTableName($currency, $coin);
		if(!Schema::hasTable($tableName)) {
			return $this->info("Table Kline not exist");
		}

		$from = $this->argument('from') ?? Carbon::now()->subDays(10)->toDateString();
		$to = $this->argument('to') ?? Carbon::now()->toDateString();

		$fromTime = Carbon::createFromFormat('Y-m-d', $from)->timestamp * 1000;
		$toTime = Carbon::createFromFormat('Y-m-d', $to)->timestamp * 1000;

		foreach (self::INTERVALS as $interval => $intervalTime) {
			$beginTime = $toTime;
			do {
				try {
					$client = new Client([
						'base_uri' => Consts::DOMAIN_BINANCE_API
					]);
					$response = $client->get('api/v3/klines', [
						'query' => [
							'symbol' => strtoupper($coin.$currency),
							'interval' => $interval,
							//'startTime' => $startTime,
							'endTime' => $beginTime,
							//'timeZone' => 1,
							'limit' => 1000
						],
						'timeout' => 5,
						'connect_timeout' => 5,
					]);

					$dataCharts = collect(json_decode($response->getBody()->getContents()));
					if (!$dataCharts->isEmpty()) {
						foreach ($dataCharts as $chart) {
							$time = $chart[0];
							$volume = BigNumber::new($chart[5])->div(5)->toString();
							$quoteVolume = BigNumber::new($chart[7])->div(5)->toString();
							$kline = DB::connection('master')->table($tableName)
								->where(['time' => $time])
								->where('interval', 'like binary', $interval)
								->first();
							if (!$kline) {
								DB::table($tableName)->insert([
									'time' => $time,
									'interval' => $interval,
									'opening_time' => $time,
									'closing_time' => $chart[6],
									'open' => $chart[1],
									'close' => $chart[4],
									'high' => $chart[2],
									'low' => $chart[3],
									'volume' => $volume,
									'quote_volume' => $quoteVolume,
									'trade_count_crawled' => $chart[8],
									'trade_count' => 0,
								]);
							} else {
								$dataUpdate = [
									'volume' => $volume,
									'quote_volume' => $quoteVolume,
									'opening_time' => $time,
									'closing_time' => $chart[6],
									'open' => $chart[1],
									'close' => $chart[4],
									'high' => $chart[2],
									'low' => $chart[3],
									'trade_count_crawled' => $chart[8],
								];
								DB::table($tableName)->where(['time' => $time, 'interval' => $interval])->update($dataUpdate);
							}

							if ($time < $beginTime) {
								$beginTime = $time + 1;
							}

						}
					} else {
						break;
					}
				} catch (\Exception $e) {}
				usleep(200000);
				echo "\nRun: {$interval} - {$beginTime}";
			} while ($beginTime >= $fromTime);
			echo "\nDone: {$interval}";
		}

		return Command::SUCCESS;
    }


	protected function getKlineTableName($currency, $coin) {
		return strtolower("klines_{$coin}_{$currency}");
	}
}
