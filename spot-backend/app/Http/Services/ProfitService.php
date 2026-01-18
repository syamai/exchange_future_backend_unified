<?php
namespace App\Http\Services;

use App\Consts;
use App\Utils;
use App\Utils\BigNumber;
use App\Models\CalculateProfit;
use App\Models\CalculateProfitForMargin;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProfitService
{
    public function getProfit($params): array
    {
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $startDate = Arr::get($params, 'start_date', 0);
        $endDate = Arr::get($params, 'end_date', 0);
        if (!array_key_exists('search_key', $params)) {
            $listCoins = DB::table('coins')->pluck('coin');
            $listCoins->push('usd');
        } else {
            $searchKey = $params['search_key'];
            $listCoins = DB::table('coins')->where('coins.coin', 'like', '%' . $searchKey . '%')->pluck('coin');
            if ($params['search_key'] == 'usd') {
                $listCoins->push('usd');
            }
        }
        $status = Arr::get($params, 'status', 'spot');
        $result = [];
        if ($status == 'spot') {
            foreach ($listCoins as $coin) {
                $data = $this->getFeeDetailFromSpotExchange($coin, $startDate, $endDate);
                array_push($result, $data);
            }
        } else {
            $listCoins = [Consts::CURRENCY_BTC, Consts::CURRENCY_AMAL];
            foreach ($listCoins as $coin) {
                $data = $this->getFeeDetailFromMarginExchange($coin, $startDate, $endDate);
                array_push($result, $data);
            }
        }
        $sortType = Arr::get($params, 'sort_type', 'desc');
        $sortFile = Arr::get($params, 'sort', 'net_fee');
        if ($sortType == "asc") {
            $result = Utils::customSort($result, $sortFile);
        } else {
            $result = array_reverse(Utils::customSort($result, $sortFile));
        }
        return Utils::customPaginate($page, $result, $limit);
    }

    public function getFeeDetailFromSpotExchange($coin, $startDate, $endDate): array
    {
        $netFee = 0;
        $receiveFee = 0;
        $referralFee = 0;
        $records = CalculateProfit::where('coin', $coin)
            ->where('date', '>=', Carbon::createFromTimestamp($startDate / 1000)->addDay()->toDateString())
            ->where('date', '<=', Carbon::createFromTimestamp($endDate / 1000)->toDateString())
            ->get();
        foreach ($records as $record) {
            $netFee = BigNumber::new($netFee)->add($record->net_fee)->toString();
            $receiveFee = BigNumber::new($receiveFee)->add($record->receive_fee)->toString();
            $referralFee = BigNumber::new($referralFee)->add($record->referral_fee)->toString();
        }
        $data = [
            'net_fee' => $netFee,
            'receive_fee' => $receiveFee,
            'referral_fee' => $referralFee,
            'coin' => $coin,
        ];
        return $data;
    }

    public function getFeeDetailFromMarginExchange($coin, $startDate, $endDate): array
    {
        $netFee = 0;
        $receiveFee = 0;
        $referralFee = 0;
        $records = CalculateProfitForMargin::where('coin', $coin)
            ->where('date', '>=', Carbon::createFromTimestamp($startDate / 1000)->addDay()->toDateString())
            ->where('date', '<=', Carbon::createFromTimestamp($endDate / 1000)->toDateString())
            ->get();
        foreach ($records as $record) {
            $netFee = BigNumber::new($netFee)->add($record->net_fee)->toString();
            $receiveFee = BigNumber::new($receiveFee)->add($record->receive_fee)->toString();
            $referralFee = BigNumber::new($referralFee)->add($record->referral_fee)->toString();
        }
        $data = [
            'net_fee' => $netFee,
            'receive_fee' => $receiveFee,
            'referral_fee' => $referralFee,
            'coin' => $coin,
        ];
        return $data;
    }
}
