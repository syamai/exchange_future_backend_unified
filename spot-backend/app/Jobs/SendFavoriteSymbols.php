<?php

namespace App\Jobs;

use App\Models\User;
use App\Events\FavoriteSymbolsUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendFavoriteSymbols extends RedisQueueJob
{
    private $userId;

    /**
     * Create a new job instance.
     *
     * @param $userId
     * @param $currencies
     */
    public function __construct($data)
    {
        $json = json_decode($data);
        logger($json[0]);
        $this->userId = $json[0];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        parent::handle();
        // we have to start transaction in order to read with isolation level read uncomitted
        DB::connection('master')->beginTransaction();
        DB::connection('master')->getPdo()->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
        try {
            $favoriteSymbols = User::on('master')->find($this->userId)->favorites;
            event(new FavoriteSymbolsUpdated($this->userId, $favoriteSymbols));
            DB::connection('master')->commit();
        } catch (\Exception $e) {
            DB::connection('master')->rollBack();
            Log::error($e);
            throw $e;
        }
    }
}
