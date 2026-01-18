<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\MarketService;
use App\Http\Services\MasterdataService;
use App\Models\CoinSetting;
use App\Models\MarketFeeSetting;
use App\Models\MarketTag;
use App\Models\Price;
use App\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Utils\BigNumber;
use Exception;

class MarketController extends AppBaseController
{

	private MarketService $marketService;

	public function __construct(MarketService $marketService)
	{
		$this->marketService = $marketService;
	}

	public function getHotSymbols()
	{
		// get value hot symbols
		$values = [];
		$hotTag = MarketTag::where('type', Consts::MARKET_TAG_HOT)->first();
		if ($hotTag) {
			$values = $hotTag->symbols ? json_decode($hotTag->symbols) : [];
		}

		$coinsDB = DB::table('coin_settings')
			->where(['is_enable' => 1])
			->get();
		$options = [];
		foreach ($coinsDB as $coin) {
			$options[] = [
				'label' => strtoupper("{$coin->coin}/{$coin->currency}"),
				'value' => "{$coin->currency}_{$coin->coin}"
			];
		}
		return $this->sendResponse(['values' => $values, 'options' => $options]);
	}

	public function updateHotSymbols(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'symbols' => 'required|array'
		]);

		if ($validator->fails()) {
			return $this->sendError($validator->messages()->first());
		}

		$data = $request->symbols ?? [];
		$coinsDB = DB::table('coin_settings')
			->where(['is_enable' => 1])
			->get();
		$options = [];
		foreach ($coinsDB as $coin) {
			$key = "{$coin->currency}_{$coin->coin}";
			$options[$key] = $key;
		}

		$symbols = [];
		foreach ($data as $v) {
			if (is_string($v) && !empty($options[$v])) {
				$symbols[$v] = $v;
			}
		}
		if (!$symbols) {
			return $this->sendError(__('Symbols is empty'));
		}
		MarketTag::updateOrCreate(
			['type' => Consts::MARKET_TAG_HOT],
			['symbols' => json_encode(array_values($symbols))]
		);
		return $this->sendResponse(true);
	}

	public function getMarkets(Request $request)
	{
		$data = $this->marketService->getMarkets($request->all());
		return $this->sendResponse($data);
	}

	public function getMarket($id)
	{
		try {
			$coinSetting = CoinSetting::find($id);
			if (!$coinSetting) {
				return $this->sendError(__('exception.not_found'));
			}

			$marketFeeSetting = MarketFeeSetting::where(
				[
					'coin' => $coinSetting->coin,
					'currency' => $coinSetting->currency
				])
				->first();

			$feeTaker = 0;
			$feeMaker = 0;
			if ($marketFeeSetting) {
				$feeTaker = $marketFeeSetting->fee_taker;
				$feeMaker = $marketFeeSetting->fee_maker;
			}

			$pair = $coinSetting->toArray();
			$pair['pair'] = $coinSetting->coin . '/' . $coinSetting->currency;
			$pair['fee_taker'] = $feeTaker;
			$pair['fee_maker'] = $feeMaker;

			return $this->sendResponse($pair);
		} catch (Exception $ex) {
			return $this->sendError('An error occurred during execution.', 400);
		}
	}

	public function updatePair($id, Request $request)
	{
		$params = $request->all();
		$regexPrecision = '/^0\.0*1{0,1}0*$/';
		$validator = Validator::make($params, [
			'is_enable' => 'required',
			'fee_taker' => 'required|numeric|min:0|max:100',
			'fee_maker' => 'required|numeric|min:0|max:100',
			'minimum_quantity' => 'required|numeric',
			'quantity_precision' => 'required|numeric|regex:' . $regexPrecision,
			'price_precision' => 'required|numeric|regex:' . $regexPrecision,
			'minimum_amount' => 'required|numeric',
			'market_price' => 'nullable|numeric|min:0',
		]);

		if ($validator->fails()) {
			return response()->json(['message' => $validator->messages(), 'errors' => $validator->errors()], 422);
		}

		$coinSetting = CoinSetting::find($id);
		if (!$coinSetting) {
			return $this->sendError(__('exception.not_found'));
		}

		$minimumQuantity = $request->minimum_quantity ?? 0;
		$quantityPrecision = $request->quantity_precision ?? 0;
		$pricePrecision = $request->price_precision ?? 0;
		$minimumAmount = $request->minimum_amount ?? 0;
		$feeTaker = $request->fee_taker ?? 0;
		$feeMaker = $request->fee_maker ?? 0;
		$isEnable = $request->is_enable ? true : false;
		$marketPrice = $request->market_price ?? $coinSetting->market_price;
		$releaseTime = $request->release_time ?? $coinSetting->release_time;
		$changeMarketPrice = BigNumber::new($coinSetting->market_price)->comp($marketPrice) !== 0;


		if ($coinSetting->is_pair_active) {
			if($changeMarketPrice || (!is_null($releaseTime)  && $coinSetting->release_time != $releaseTime)) {
				return $this->sendError(__('exception.no_permission'), 403);
			}
		}

		DB::beginTransaction();
		try {

			$coinSetting->update([
				'minimum_quantity' => $minimumQuantity,
				'quantity_precision' => $quantityPrecision,
				'price_precision' => $pricePrecision,
				'minimum_amount' => $minimumAmount,
				'is_enable' => $isEnable,
				'market_price' => $marketPrice,
				'release_time' => $releaseTime
			]);

			//update fee
			MarketFeeSetting::updateOrCreate(
				[
					'currency' => $coinSetting->currency,
					'coin' => $coinSetting->coin,
				],
				[
					'fee_taker' => $feeTaker,
					'fee_maker' => $feeMaker
				]
			);

			DB::commit();

			MasterdataService::clearCacheOneTable('coin_settings');
			MasterdataService::clearCacheOneTable('market_fee_setting');

			if ($changeMarketPrice && $marketPrice) {
				Price::where(
					[
						'currency' => $coinSetting->currency,
						'coin' => $coinSetting->coin,
						'is_market' => 1
					])
					->update([
						'price' => $marketPrice,
					]);
			}

			$coinSetting->refresh();
			$pair = $coinSetting->toArray();
			$pair['pair'] = $coinSetting->coin . '/' . $coinSetting->currency;
			$pair['fee_taker'] = $feeTaker;
			$pair['fee_maker'] = $feeMaker;
			return $this->sendResponse($pair, 'Success');
		} catch (Exception $ex) {
			DB::rollBack();
			return $this->sendError('An error occurred during execution.', 400);
		}
	}

	public function createPair(Request $request)
	{
		$params = $request->all();
		$regexPrecision = '/^0\.0*1{0,1}0*$/';
		$validator = Validator::make($params, [
			'coin' => 'required|alpha_num|exists:coins,coin',
			'currency' => 'required|alpha_num|exists:coins,coin|different:coin',
			'fee_taker' => 'required|numeric|min:0|max:100',
			'fee_maker' => 'required|numeric|min:0|max:100',
			'minimum_quantity' => 'required|numeric',
			'quantity_precision' => 'required|numeric|regex:' . $regexPrecision,
			'price_precision' => 'required|numeric|regex:' . $regexPrecision,
			'minimum_amount' => 'required|numeric',
			'market_price' => 'required|numeric|min:0',
		]);

		if ($validator->fails()) {
			return response()->json(['message' => $validator->messages(), 'errors' => $validator->errors()], 422);
		}

		$coin = strtolower($request->coin ?? '');
		$currency = strtolower($request->currency ?? '');
		$feeTaker = $request->fee_taker ?? 0;
		$feeMaker = $request->fee_maker ?? 0;
		$minimumQuantity = $request->minimum_quantity ?? 0;
		$quantityPrecision = $request->quantity_precision ?? 0;
		$pricePrecision = $request->price_precision ?? 0;
		$minimumAmount = $request->minimum_amount ?? 0;
		$marketPrice = $request->market_price ?? 0;
		$precision = $request->precision ?? '0.0001';
		$isEnable = $request->is_enable ?? false;
		$isEnable = $isEnable ? true : false;
		$releaseTime = $request->release_time ?? Utils::currentMilliseconds();


		//check exist
		$coinSetting = CoinSetting::where(
			[
				'coin' => $coin,
				'currency' => $currency
			])
			->first();
		if ($coinSetting) {
			return $this->sendError(__('exception.market_was_exist'));
		}

		DB::beginTransaction();
		try {
			// insert coin setting
			$coinSetting = CoinSetting::create([
				'coin' => $coin,
				'currency' => $currency,
				'minimum_quantity' => $minimumQuantity,
				'quantity_precision' => $quantityPrecision,
				'price_precision' => $pricePrecision,
				'minimum_amount' => $minimumAmount,
				'is_enable' => $isEnable,
				'release_time' => $releaseTime,
				'market_price' => $marketPrice
			]);
			DB::commit();

			$pair = $coinSetting->toArray();
			$pair['pair'] = $coinSetting->coin . '/' . $coinSetting->currency;
			$pair['fee_taker'] = $feeTaker;
			$pair['fee_maker'] = $feeMaker;

			// insert fee pair
			MarketFeeSetting::updateOrCreate(
				[
					'currency' => $coinSetting->currency,
					'coin' => $coinSetting->coin,
				],
				[
					'fee_taker' => $feeTaker,
					'fee_maker' => $feeMaker
				]
			);

			// insert price group
			$this->marketService->insertPriceGroups($coin, $currency, $precision);

			// set market price
			DB::table("prices")->insert([
				'currency' => $currency,
				'coin' => $coin,
				'price' => $marketPrice,
				'quantity' => '0',
				'amount' => '0',
				'is_market' => 1,
				'created_at' => Utils::currentMilliseconds()
			]);

			//create table klines
			$this->marketService->createKlineTable($coin, $currency);
			$this->marketService->cacheClear();

			//send pair to ME
			try {
				$pairs = [
					'type' => "pair",
					'data' => [
						[
							'currency' => $coinSetting->currency,
							'coin' => $coinSetting->coin,
							'minimum_quantity' => $coinSetting->minimum_quantity,
							'price_precision' => $coinSetting->price_precision,
							'minimum_amount' => $coinSetting->minimum_amount,
							'quantity_precision' => $coinSetting->quantity_precision,
							'maker_fee_percent' => $feeMaker,
							'taker_fee_percent' => $feeTaker
						]
					]
				];
				Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_INIT, $pairs);
			} catch (Exception $exx) {}


			return $this->sendResponse($pair, 'Success');

		} catch (Exception $ex) {
			DB::rollBack();
			return $this->sendError('An error occurred during execution.', 400);
		}
	}
}
