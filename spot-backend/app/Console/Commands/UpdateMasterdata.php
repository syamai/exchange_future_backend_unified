<?php

namespace App\Console\Commands;

use App\Consts;
use App\Utils;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use function PHPUnit\Framework\throwException;

class UpdateMasterdata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'master:update {lang?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update masterdata from json file to database';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $BATCH_SIZE = 500;
        DB::beginTransaction();
        try {
            $language = $this->argument('lang');
            $filename = empty($language) ? 'latest.json' : 'latest_' . $language . '.json';
            $path = storage_path('masterdata/' . $filename);
            $jsonData = json_decode(file_get_contents($path), true);
            foreach ($jsonData as $table => $values) {
                if (Schema::hasTable($table)) {
                    printf("Update table: %s\n", $table);
                    $chunks = array_chunk($values, $BATCH_SIZE);
                    $prices = [];
                    for ($chunkIndex = 0; $chunkIndex < count($chunks); $chunkIndex++) {
                        $chunk = $chunks[$chunkIndex];
                        for ($index = 0; $index < count($chunk); $index++) {
                            $now = Carbon::now('utc')->toDateTimeString();
                            $chunk[$index]['created_at'] = $now;
                            $chunk[$index]['updated_at'] = $now;

                            if ($table == 'coins') {
//                                $coin = Consts::PARSE_COIN_IDS[$chunk[$index]['coin']];
//                                $results = $this->getPricesCoingecko($coin);
                                foreach (Consts::PAIRS as $curreny) {
                                    $prices[] = [
                                        'currency' => $curreny,
                                        'coin' => $chunk[$index]['coin'],
//                                        'price' => $results->$coin->$curreny ?? $results->$coin->usd,
                                        'quantity' => '0',
                                        'amount' => '0',
                                        'created_at' => Utils::currentMilliseconds(),
                                    ];
                                }
                            }
                        }
                        DB::table($table)->insert($chunk);
                        if ($table == 'coins') {
                            DB::table("prices")->insert($prices);
                        }
                    }
                }
            }

            DB::commit();
            Cache::flush();
        } catch (Exception $e) {
            DB::rollBack();
            printf($e);
        }
    }

    public function getPricesCoingecko($coin)
    {
        try {
            $client = new Client([
                'base_uri' => env('DOMAIN_COINGECKO_API')
            ]);

            $response = $client->get('simple/price', [
                'query' => [
                    'ids' => $coin,
                    'vs_currencies' => implode(",",Consts::PAIRS)
                ]
            ]);

            return json_decode($response->getBody());
        } catch (Exception $e) {
            Log::error($e);
            throw $e;
        }
    }
}
