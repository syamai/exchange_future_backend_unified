<?php

namespace App\Jobs;

use App\Consts;
use App\Http\Services\MasterdataService;
use App\Models\CoinsConfirmation;
use App\Models\CoinSetting;
use App\Models\Instrument;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Schema;

class CreateUserAccounts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::beginTransaction();
        try {
            $userId = $this->id;
            $this->createUserUsdAccount($userId);
            $this->createTagAccount('xrp', $userId);
            $this->createTagAccount('eos', $userId);

            foreach (CoinsConfirmation::pluck('coin') as $coin) {
                logger('CreateUserAccounts ' . $coin);
                $isCreate = $coin != Consts::CURRENCY_XRP && $coin != Consts::CURRENCY_EOS && $coin != Consts::CURRENCY_USD;

                if ($isCreate) {
                    $this->createUserAccount($coin, $userId);
                }
            }

            foreach (Consts::AIRDROP_TABLES as $coin) {
                $this->createAirdropAccount($coin, $userId);
            }


            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            logger()->error("Create user account $this->id error:" . $e->getMessage());
            throw $e;
        }
    }

    private function createUserUsdAccount($userId)
    {
        $tables = [
            'usd_accounts',
            'spot_usd_accounts',
            // 'mam_usd_accounts',
        ];

        foreach ($tables as $table) {
            if (!$this->checkHasTable($table)) {
                continue;
            }

            $record = DB::table($table)->where('id', $userId)->first();
            if (!$record) {
                DB::table($table)->insert([
                    'id' => $userId,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
        }
    }

    private function createUserAccount($currency, $userId)
    {
        $table = "{$currency}_accounts";
        if ($this->checkHasTable($table)) {
            $record = DB::table($table)->where('id', $userId)->first();
            if (!$record) {
                DB::table($table)->insert([
                    'id' => $userId,
                    'blockchain_address' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
        }

        $table = "spot_{$currency}_accounts";
        if ($this->checkHasTable($table)) {
            $record = DB::table($table)->where('id', $userId)->first();
            if (!$record) {
                DB::table($table)->insert([
                    'id' => $userId,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
        }

        if ($currency == Consts::CURRENCY_BTC) {
            $table = "margin_accounts";
            if ($this->checkHasTable($table)) {
                $record = DB::table($table)->where('owner_id', $userId)->first();
                if (!$record) {
                    DB::table($table)->insert([
                        'owner_id' => $userId,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
                }
            }
        }

        if ($currency == Consts::CURRENCY_AMAL) {
            $table = "amal_margin_accounts";
            if ($this->checkHasTable($table)) {
                $record = DB::table($table)->where('owner_id', $userId)->first();
                if (!$record) {
                    DB::table($table)->insert([
                        'owner_id' => $userId,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
                }
            }
        }

        // TODO: Insert data to MAM Table
        //DB::table("mam_{$currency}_accounts")->insert([
        //    'id' => $userId,
        //    'created_at' => Carbon::now(),
        //    'updated_at' => Carbon::now()
        //]);
    }

    private function createTagAccount($currency, $userId)
    {
        $table = "{$currency}_accounts";
        if ($this->checkHasTable($table)) {
            $record = DB::table($table)->where('id', $userId)->first();
            if (!$record) {
                DB::table($table)->insert([
                    'id' => $userId,
                    'blockchain_address' => null,
                    'blockchain_sub_address' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
        }

        $table = "spot_{$currency}_accounts";
        if ($this->checkHasTable($table)) {
            $record = DB::table($table)->where('id', $userId)->first();
            if (!$record) {
                DB::table($table)->insert([
                    'id' => $userId,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
        }

        if ($currency == Consts::CURRENCY_BTC) {
            $table = "margin_accounts";
            if ($this->checkHasTable($table)) {
                $record = DB::table($table)->where('id', $userId)->first();
                if (!$record) {
                    DB::table($table)->insert([
                        'id' => $userId,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
                }
            }
        }

        // TODO: Insert data to MAM Table
        //DB::table("mam_{$currency}_accounts")->insert([
        //    'id' => $userId,
        //    'created_at' => Carbon::now(),
        //    'updated_at' => Carbon::now()
        //]);
    }

    private function createAirdropAccount($currency, $userId)
    {
        $table = "airdrop_{$currency}_accounts";
        if ($this->checkHasTable($table)) {
            $record = DB::table($table)->where('id', $userId)->first();
            if (!$record) {
                DB::table($table)->insert([
                    'id' => $userId,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
        }
    }

    private function checkHasTable($tableName)
    {
        return $hasTable = Schema::hasTable($tableName);
    }
}
