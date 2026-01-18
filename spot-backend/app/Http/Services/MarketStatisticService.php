<?php

namespace App\Http\Services;

use App\Consts;
use App\Models\MarketStatistic;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MarketStatisticService
{
    private MarketStatistic $model;
    private PriceService $priceService;

    public function __construct(MarketStatistic $model, PriceService $priceService)
    {
        $this->model = $model;
        $this->priceService = $priceService;
    }

    public function getMarketStatistic()
    {
        $result = [];
        $gainer = $this->getGainer();
        $loser = $this->getLoser($gainer['coin_gainer'] ?? []);

        $volume = $this->getTopVolume();
        $listing = $this->getTopListing();
        $result['gainer'] = $gainer['gainer'] ?? [];

        $result['loser'] = $loser;
        $result['volume'] = $volume;
        $result['listing'] = $listing;

        usort($result['gainer'], function ($firstItem, $secondItem) {
            return $firstItem->top > $secondItem->top;
        });
        usort($result['loser'], function ($firstItem, $secondItem) {
            return $firstItem->top > $secondItem->top;
        });
        usort($result['volume'], function ($firstItem, $secondItem) {
            return $firstItem->top > $secondItem->top;
        });
        usort($result['listing'], function ($firstItem, $secondItem) {
            return $firstItem->top > $secondItem->top;
        });

        return $result;
    }

    private function getGainer()
    {
        $gainer = [];
        $gainers = DB::select("SELECT *
            FROM market_gainers
            WHERE `id` = (
                SELECT `id`
                FROM market_gainers as `alt`
                WHERE `alt`.`name` = market_gainers.`name`
                ORDER BY created_at DESC
                LIMIT 1
            )
            ORDER BY created_at DESC
            LIMIT 3");

        $coinGainers = collect($gainers)->map(function ($item) {
            return $item->name;
        })->toArray();

        if (count($gainers) > 0) {
            foreach ($gainers as $item) {
                $checkPriceItem = $this->checkLastPriceAndChangedMarket($item);
                $gainer[] = $checkPriceItem;
            }
        }

        return [
            'gainer' => $gainer,
            'coin_gainer' => $coinGainers ?? []
        ];
    }

    public function checkLastPriceAndChangedMarket($item)
    {
        $price = $this->priceService->getPrice('usdt', $item->name);
        $item->lastest_price = $price->price ?? 0;
        $item->previous_price = $price->previous_price ?? 0;
        $item->last_24h_price = $price->last_24h_price ?? 0;
        $item->changed_percent = $price->change ?? 0 ;

        return $item;
    }

    private function getLoser($coinGainers): array
    {
        $loser = [];
        $losers = DB::select("SELECT *
            FROM market_losers
            WHERE `id` = (
                SELECT `id`
                FROM market_losers as `alt`
                WHERE `alt`.`name` = market_losers.`name` AND `alt`.`name` NOT IN (?, ?, ?)
                ORDER BY created_at DESC
                LIMIT 1
            )
            ORDER BY created_at DESC LIMIT 3", [$coinGainers[0] ?? '', $coinGainers[1] ?? '', $coinGainers[2] ?? '']);


        if (count($losers) > 0) {
            foreach ($losers as $item) {
                $checkPriceItem = $this->checkLastPriceAndChangedMarket($item);
                $loser[] = $checkPriceItem;
            }
        }

        return $loser;
    }

    private function getTopVolume(): array
    {
        $dataRtn = [];
        $volumes = DB::select("SELECT *
            FROM market_volumes
            WHERE `id` = (
                SELECT `id`
                FROM market_volumes as `alt`
                WHERE `alt`.`name` = market_volumes.`name`
                ORDER BY created_at DESC
                LIMIT 1
            )
            ORDER BY created_at DESC
            LIMIT 3");

        foreach ($volumes as $volume) {
            $price = $this->checkLastPriceAndChangedMarket($volume);
            $dataRtn[] = $price;
        }

        return $dataRtn;
    }

    private function getTopListing(): array
    {
        $dataRtn = [];
        $listings = DB::select("SELECT *
            FROM market_listings
            WHERE `id` = (
                SELECT `id`
                FROM market_listings as `alt`
                WHERE `alt`.`name` = market_listings.`name`
                ORDER BY created_at DESC
                LIMIT 1
            )
            ORDER BY created_at DESC
            LIMIT 3");

        foreach ($listings as $listing) {
            $price = $this->checkLastPriceAndChangedMarket($listing);
            $dataRtn[] = $price;
        }

        return $dataRtn;
    }

    public function checkDuplicateGainerLoser($gainer, $loser): object
    {
        foreach ($gainer as $keyGainer => $itemGainer) {
            foreach ($loser as $keyLoser => $itemLoser) {
                if ($itemGainer->name == $itemLoser->name) {
                    if ($itemGainer->is_new == 1 && $itemLoser->is_new == 0) {
                        unset($loser[$keyLoser]);
                        $loser = array_values($loser);
                    }
                    if (($itemLoser->is_new == 1 && $itemGainer->is_new == 0) || ($itemGainer->is_new == 0 && $itemLoser->is_new == 0)) {
                        unset($gainer[$keyGainer]);
                        $gainer = array_values($gainer);
                    }
                }
            }
        }
        return (object)['gainer' => $gainer, 'loser' => $loser];
    }
}
