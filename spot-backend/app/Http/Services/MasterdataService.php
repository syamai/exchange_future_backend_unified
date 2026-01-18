<?php

namespace App\Http\Services;

use App\Consts;
use App\Models\MamSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class MasterdataService
{
    protected static $localData = null;
    protected static $localDataVersion = null;

    public static function getDataVersion()
    {
        if (Cache::has('dataVersion')) {
            return Cache::get('dataVersion');
        }

        self::getAllData();
        return Cache::get('dataVersion');
    }

    public static function isUpToDate()
    {
        $remoteDataVersion = Cache::get('dataVersion', null);
        return $remoteDataVersion === self::$localDataVersion;
    }

    public static function getAllData()
    {
        if (!self::isUpToDate()) {
            self::$localData = null;
        }

        if (self::$localData != null) {
            return self::$localData;
        }
        $keySetting = 'masterdata_vesion';
        $checkDataVesionDB = env('CHECK_DATA_VESION_DB', true);

        if (Cache::has('dataVersion') && $checkDataVesionDB) {
			// get database
			$settings = MamSetting::on('master')->where('key', $keySetting)->first();
			if (!$settings || $settings->value !== Cache::get('dataVersion')) {
				Cache::forget('dataVersion');
				Cache::forget('masterdata');
				//dd(Cache::get('dataVersion'));
			}
		}

        if (Cache::has('masterdata') && Cache::has('dataVersion')) {
            if (self::$localData == null) {
                self::$localData = Cache::get('masterdata');
            }

            return self::$localData;
        }

        $data = [];

        foreach (Consts::MASTERDATA_TABLES as $table) {
            if (Schema::hasTable($table)) {
                if (Schema::hasColumn($table, 'id')) {
                    $data[$table] = DB::connection('master')->table($table)->orderBy('id', 'asc')->get();
                } else {
                    $data[$table] = DB::connection('master')->table($table)->get();
                }
            }
        }

        Cache::forever('masterdata', $data);
        $dataVersion = sha1(json_encode($data));
        if ($checkDataVesionDB) {
			MamSetting::on('master')->updateOrCreate(
				['key' => $keySetting],
				['key' => $keySetting, 'value' => $dataVersion]
			);
		}
        Cache::forever('dataVersion', $dataVersion);
        return $data;
    }

    public static function getOneTable($table)
    {
        $key = 'masterdata_' . $table;
        if (Cache::has($key)) {
            return collect(Cache::get($key));
        }

        $data = [];
        $allData = self::getAllData();
        if (!empty($allData[$table])) {
            $data = $allData[$table];
            Cache::forever($key, $data);
        }

        return collect($data);
    }

    public static function getCurrencies()
    {
        $curencyCoins = static::getOneTable('coin_settings');
        return $curencyCoins->map(function ($item) {
            return $item->currency;
        })->unique()->values()->toArray();
    }

    public static function getCoins()
    {
        $curencyCoins = static::getOneTable('coin_settings');
        return $curencyCoins->map(function ($item) {
            return $item->coin;
        })->unique()->values()->toArray();
    }

    public static function getCoinId($coin)
    {
        $coins = static::getOneTable('coins');
        if ($coin === Consts::CURRENCY_USD) {
            return 0;
        }
        foreach ($coins as $coinData) {
            if ($coinData->coin === $coin) {
                return $coinData->id;
            }
        }
        return -1;
    }

    public static function getCurrenciesAndCoins()
    {
        return collect(static::getCurrencies())->merge(collect(static::getCoins()))->unique()->all();
    }

    public static function clearCacheOneTable($table)
    {
        static::$localData = null;
        Cache::forget("masterdata_$table");
        Cache::forget('dataVersion');
        Cache::forget('masterdata');
    }

    public static function getCoinConfirmation($coin)
    {
        $coinsConfirmation = static::getOneTable('coins_confirmation');
        $result = $coinsConfirmation->first(function ($item) use ($coin) {
            return $item->coin == $coin;
        });
        return $result ? $result->confirmation : null;
    }
}
