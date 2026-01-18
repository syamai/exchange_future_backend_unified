<?php

namespace App\Http\Services;

use App\Consts;
use App\Models\CoinSetting;
use App\Models\PriceGroup;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarketService
{
	public function getMarkets($params)
	{
		$limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
		return CoinSetting::leftJoin('market_fee_setting',
			function ($join) {
				$join->on('coin_settings.currency', '=', 'market_fee_setting.currency');
				$join->on('coin_settings.coin', '=', 'market_fee_setting.coin');
			})
			->when(!empty($params['currency']), function ($query) use ($params) {
				$query->where('coin_settings.currency', '=', $params['currency']);
			})
			->when(!empty($params['coin']), function ($query) use ($params) {
				$query->where('coin_settings.coin', '=', $params['coin']);
			})
			->select('coin_settings.id', DB::raw("concat(coin_settings.coin, '/', coin_settings.currency) as pair"), 'coin_settings.coin', 'coin_settings.currency', 'market_fee_setting.fee_taker', 'market_fee_setting.fee_maker', 'coin_settings.created_at', 'coin_settings.is_enable')
			->orderByDesc('id')
			->paginate($limit);
	}

	public function insertPriceGroups($coin, $currency, $precision) {
		if (DB::table('price_groups')->where('coin', $coin)->where('currency', $currency)->count() > 0) {
			return;
		}


		$data = [
			[
				'coin' => $coin,
				'currency' => $currency,
				'group' => 3,
				'value' => ($precision *= 10)
			],
			[
				'coin' => $coin,
				'currency' => $currency,
				'group' => 2,
				'value' => ($precision *= 10)
			],
			[
				'coin' => $coin,
				'currency' => $currency,
				'group' => 1,
				'value' => ($precision *= 10)
			],
			[
				'coin' => $coin,
				'currency' => $currency,
				'group' => 0,
				'value' => ($precision *= 10)
			],
		];

		foreach ($data as $datum) {
			PriceGroup::create($datum);
		}
	}

	public function createKlineTable($coin, $currency)
	{
		$tableName = strtolower("klines_{$coin}_{$currency}");
		if(!Schema::hasTable($tableName)) {
			$params = [$tableName];
			$sqlParams = implode(',', array_fill(0, sizeof($params), '?'));
			DB::connection('master')->update('CALL create_table_kline(' . $sqlParams . ')', $params);
		}
	}

	public function cacheClear()
	{
		MasterdataService::clearCacheOneTable('coin_settings');
		MasterdataService::clearCacheOneTable('market_fee_setting');
		MasterdataService::clearCacheOneTable('price_groups');

		//Artisan::call('cache:clear');
		//Artisan::call('view:clear');
	}
}