<?php
namespace App\Http\Services;

use App\Consts;
use App\Models\AMALNetStatistic;
use App\Utils;
use App\Models\User;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class AmalNetService
{
    public function getAMALNet($params): array
    {
        $page = Arr::get($params, 'page', 1);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $start_date = Arr::get($params, 'start_date', 0);
        $end_date = Arr::get($params, 'end_date', 0);
        $startDate = Carbon::createFromTimestamp($start_date / 1000)->addDay()->toDateString();
        $endDate = Carbon::createFromTimestamp($end_date / 1000)->toDateString();
        $users = User::where('status', Consts::USER_ACTIVE)->get();
        $result = [];
        $index = 0;
        foreach ($users as $user) {
            $res = $this->calculateAMALNet($user->id, $startDate, $endDate);
            $AMALIn = $res['amal_in'];
            $AMALOut = $res['amal_out'];
            $AMALNet = 0;
            if (BigNumber::new($AMALIn)->comp($AMALOut) > 0) {
                $AMALNet = BigNumber::new($AMALIn)->sub($AMALOut)->toString();
            }
            $data = [
                'AMAL_in' => $AMALIn,
                'AMAL_out' => $AMALOut,
                'AMAL_net' => $AMALNet,
                'email' => $user->email,
                'index' => $index
            ];
            array_push($result, $data);
        }
        $sortType = Arr::get($params, 'sort_type', 'desc');
        $sortFile = Arr::get($params, 'sort', 'email');
        if ($sortType == "asc") {
            $result = Utils::customSort($result, $sortFile);
        } else {
            $result = array_reverse(Utils::customSort($result, $sortFile));
        }
        foreach ($result as &$item) {
            $index++;
            $item['index'] = $index;
        }
        $data = [];
        if (array_key_exists('search_key', $params)) {
            $searchKey = $params['search_key'];
            foreach ($result as &$r) {
                if (strpos($r['email'], $searchKey) !== false) {  // mustn't delete 'false'
                    array_push($data, $r);
                }
            }
        } else {
            $data = $result;
        }
        return Utils::customPaginate($page, $data, $limit);
    }
    public function calculateAMALNet($userId, $startDate, $endDate): array
    {
        $totalAMALIn = 0;
        $totalAMALOut = 0;
        $records = AMALNetStatistic::where('user_id', $userId)
            ->where('statistic_date', '>=', $startDate)
            ->where('statistic_date', '<=', $endDate)
            ->get();
        foreach ($records as $record) {
            $totalAMALIn = BigNumber::new($totalAMALIn)->add($record->amal_in)->toString();
            $totalAMALOut = BigNumber::new($totalAMALOut)->add($record->amal_out)->toString();
        }
        return $result = [
            'amal_in' => $totalAMALIn,
            'amal_out' => $totalAMALOut,
        ];
    }
}
