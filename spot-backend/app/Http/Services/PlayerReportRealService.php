<?php

namespace App\Http\Services;

use App\Consts;
use App\Models\PlayerRealBalanceReport;
use App\Utils;
use Error;
use Illuminate\Support\Arr;
use DataExport;

class PlayerReportRealService
{
    public function jobReportBalance() {}
    public function balance($request)
    {
        try {

            $limit = Arr::get($request, 'limit', Consts::DEFAULT_PER_PAGE);
            $sort =  Arr::get($request, 'sort', 'last_login_at');
            $direction =  Arr::get($request, 'direction', Consts::SORT_DESC);

            $suid = Arr::get($request, 'uid', null);

            return PlayerRealBalanceReport::query()
                ->selectRaw('*')
                ->when($suid, fn($q) => $q->where('uid', $suid))
                ->orderBy($sort, $direction)
                ->paginate($limit)
                ->withQueryString();
        } catch (Error $e) {
            throw new Error($e->getMessage());
        }
    }

    public function export($request)
    {
        try {
            $page = Arr::get($request, 'page', 0);
            $ext = Arr::get($request, 'ext', 'csv');
            $now = Utils::currentMilliseconds();
            
            if ($page == -1) {
                $sort =  Arr::get($request, 'sort', 'last_login_at');
                $direction =  Arr::get($request, 'direction', Consts::SORT_DESC);
                $suid = Arr::get($request, 'uid', null);
                

                $data = PlayerRealBalanceReport::query()
                    ->selectRaw('*')
                    ->when($suid, fn($q) => $q->where('uid', $suid))
                    ->orderBy($sort, $direction)->get();

                $headers = collect($data->first())->keys();
                $data = $data->prepend($headers);
                $data = $data->toArray();
                $params = [
                    'fileName' => "Player_real_balance_report_all_{$now}",
                    'data' => $data,
                    'ext' => $ext,
                    'headers' => [
                        'X-Custom-Export' => 'Yes'
                    ],
                ];

                return DataExport::export($params);
            }

            $data = $this->balance($request);

            $headers = collect($data->first())->keys();
            $data = $data->prepend($headers);
            $data = $data->toArray();
            // dd($data);
            $params = [
                    'fileName' => "Player_real_balance_report_filter_{$now}",
                    'data' => $data,
                    'ext' => $ext,
                    'headers' => [
                        'X-Custom-Export' => 'Yes'
                    ],
                ];

            return DataExport::export($params);

        } catch (Error $e) {
            throw new Error($e->getMessage());
        }
    }
}
