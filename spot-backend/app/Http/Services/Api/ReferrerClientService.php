<?php

namespace App\Http\Services\Api;

use App\Consts;
use App\Models\AffiliateTrees;
use App\Models\ReferrerClientLevel;
use App\Models\ReferrerClientLevelLog;
use App\Models\ReferrerRecentActivitiesLog;
use App\Models\ReportDailyReferralClient;
use App\Models\ReportReferralCommissionRanking;
use App\Models\ReportTransaction;
use App\Models\User;
use App\Models\UserRates;
use App\Models\UserSamsubKyc;
use App\Utils;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use DataExport;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReferrerClientService
{
    public function tierProgress($request)
    {
        $start = Carbon::now()->startOfMonth()->timestamp * 1000;
        $end   = Carbon::now()->endOfDay()->timestamp * 1000;

        $sDate = Carbon::now()->startOfMonth()->toDateString();
        $eDate = Carbon::now()->endOfDay()->toDateString();

        $userId = $request->user()->id;
        $referrals_pass = User::where('referrer_id', $userId)
        // ->whereHas('userSamsubKYC', fn($q) => $q->where('status', UserSamsubKyc::VERIFIED_STATUS))
        ->whereHas('reportTransactions', function ($q) use ($sDate, $eDate) {
            $q->whereBetween('date', [$sDate, $eDate])
            ->where('volume', '>', 0);
        })
        ->count();

        $progress = ReferrerClientLevel::filterProgress();
        $basic = collect($progress)->first();

        $referrals = AffiliateTrees::with('userDown')
        ->where('referrer_id', $userId)
        ->where('level', 1)
        /*
        ->whereHas('userDown.userSamsubKYC', function ($q) {
            $q->where('status', UserSamsubKyc::VERIFIED_STATUS);
        })
        */
        ->select('user_id')
        ->distinct()
        ->pluck('user_id');

        // dd(collect($referrals)->toJson());

        $totalVolumeReferralPass = ReportTransaction::query()
            ->whereIn('user_id', $referrals)
            ->where('volume', '>', 0)
            ->whereBetween('date', [$sDate, $eDate])
            ->sum('volume');

        $owner = UserRates::find($userId);

        $data = collect();

        if (!$owner) {
            $_owner = [
                'level' => $basic->get('level'),
                'rate' =>  $basic->get('rate'),
                'referral_pass' => $referrals_pass,
                'volume_referral_pass' => $totalVolumeReferralPass ?? 0,
                'progress' => $basic
            ];
        } else {
            $_owner = [
                'level' => $owner->referral_client_level,
                'rate' =>  $owner->referral_client_rate,
                'referral_pass' => $referrals_pass,
                'volume_referral_pass' => $totalVolumeReferralPass ?? 0,
                'progress' => $progress[$owner->referral_client_level]
            ];
        }

        $data->put('owner', $_owner);
        $data->put('progress', $progress);

        return $data;
    }

    public function recentActivities($request)
    {
        $limit = Arr::get($request, 'limit', Consts::DEFAULT_PER_PAGE);

        $referrerId = $request->user()->id;
        $data = ReferrerRecentActivitiesLog::query()
            ->where('user_id', $referrerId)
            ->select(['activities', 'details', 'log_at', 'created_at'])
            ->orderby('log_at', 'desc')
            ->paginate($limit)
            ->withQueryString();

        return $data;
    }

    public function recentActivitiesExport($request) {
        
        $page = Arr::get($request, 'page', 0);
        $ext = Arr::get($request, 'ext', 'csv');
        $now = Utils::currentMilliseconds();

        if ($page == -1) {
            $referrerId = $request->user()->id;
            $data = ReferrerRecentActivitiesLog::query()
                ->where('user_id', $referrerId)
                ->select(['activities', 'details', 'log_at', 'created_at'])
                ->orderby('log_at', 'desc')->get();

            $headers = collect($data->first())->keys();
            $data = $data->prepend($headers);
            $data = $data->toArray();
            
            $params = [
                    'fileName' => "reccent_activities_report_all_{$now}",
                    'data' => $data,
                    'ext' => $ext,
                    'headers' => [
                        'X-Custom-Export' => 'Yes'
                    ],
                ];
            return DataExport::export($params);
        }
        
        $data = $this->recentActivities($request);
        $headers = collect($data->first())->keys();
        $data = $data->prepend($headers);
        $data = $data->toArray();

        $params = [
            'fileName' => "reccent_activities_report_{$now}",
                    'data' => $data,
                    'ext' => $ext,
                    'headers' => [
                        'X-Custom-Export' => 'Yes'
                    ],
                ];

        return DataExport::export($params);
    }

    public function leaderboard($request)
    {
        $owner = collect();
        $owner->put('detail', $this->owner($request));
        $owner->put('ranking', $this->rankingChanged($request));
        
        return [
            'owner' => $owner,
            'top' => [
                'up' => $this->topUp(),
                'down' => $this->topDown()
            ]
        ];
    }

    public function owner($request)
    {
        $latestWeek = ReportReferralCommissionRanking::lastWeek();

        if (!$latestWeek) return null;

        $data = DB::table('report_referral_commission_ranking')
            ->select(['rank', 'uid', 'referrals', 'registration_at', 'total_volume_value', 'total_commission_value', 'tier'])
            ->where('year', $latestWeek->year)
            ->where('week', $latestWeek->week)
            ->where('user_id', $request->user()->id)
            ->first();

        $totalRanking = ReportReferralCommissionRanking::maxRank();
        
        $data = collect($data)->toArray();
        $data['total_rank'] = $totalRanking;
        
        return $data;
    }

    public function rankingChanged($request) {
        $latestWeek = ReportReferralCommissionRanking::lastWeek();

        if (!$latestWeek) return collect();

        $prevWeek = $latestWeek->week - 1;
        $prevYear = $latestWeek->year;

        if ($prevWeek <= 0) {
            // Get last ISO week of previous year
            $prevYear--;
            $prevWeek = Carbon::parse("{$prevYear}-12-28")->isoWeek(); // ISO week of last week of year
        }

        $weeks = [
            ['year' => $prevYear, 'week' => $prevWeek],
            ['year' => $latestWeek->year, 'week' => $latestWeek->week],
        ];

        $data = DB::table('report_referral_commission_ranking')
            ->where(function ($q) use ($weeks) {
                foreach ($weeks as $w) {
                    $q->orWhere(function ($qq) use ($w) {
                        $qq->where('year', $w['year'])
                        ->where('week', $w['week']);
                    });
                }
            })
        ->where('user_id', $request->user()->id)
        ->get(['rank', 'week', 'year']);

        return $data;
    }

    public function topUp()
    {
        $latestWeek = ReportReferralCommissionRanking::lastWeek();
        if (!$latestWeek) return [];
        
        $data = DB::table('report_referral_commission_ranking')
            ->select(['rank', 'uid', 'referrals', 'registration_at', 'total_volume_value', 'total_commission_value', 'tier'])
            ->where('year', $latestWeek->year)
            ->where('week', $latestWeek->week)
            ->orderBy('rank', 'asc')
            ->limit(3)
            ->get();

        return $data;
    }

    public function topDown()
    {
        $latestWeek = ReportReferralCommissionRanking::lastWeek();
        if (!$latestWeek) return [];

        $data = DB::table('report_referral_commission_ranking')
            ->select(['rank', 'uid', 'referrals', 'registration_at', 'total_volume_value', 'total_commission_value', 'tier'])
            ->where('year', $latestWeek->year)
            ->where('week', $latestWeek->week)
            ->orderBy('rank', 'desc')
            ->take(3)
            ->get();

        return $data;
    }

    public function rankingListWeekSub($request)
    {
        $limit = Arr::get($request, 'limit', Consts::DEFAULT_PER_PAGE);
        $direct = Arr::get($request, 'direct', 'asc');
        $sort = Arr::get($request, 'sort', 'rank');
       $latestWeek = ReportReferralCommissionRanking::lastWeek();

        if (!$latestWeek) return [];

        $data = DB::table('report_referral_commission_ranking')
            ->select(['rank', 'uid', 'referrals', 'registration_at', 'total_volume_value', 'total_commission_value', 'tier', 'reported_at'])
            ->where('year', $latestWeek->year)
            ->where('week', $latestWeek->week)
            ->orderBy($sort, $direct)
            ->paginate($limit)
            ->withQueryString();

        return ['last_updated' => $latestWeek->last_updated, 'data' => $data];
    }

    public function dailyCommission($validated, $request)
    {
        $referrerId = $request->user()->id;

        // $sdate = Carbon::createFromTimestamp($validated['sdate'])->startOfDay();
        // $edate = Carbon::createFromTimestamp($validated['edate'])->endOfDay();

        $sdate = Carbon::createFromDate($validated['sdate'])->toDateString();
        $edate = Carbon::createFromDate($validated['edate'])->toDateString();

        // dd($sdate, $edate);

        $data = ReportTransaction::query()
            ->whereBetween('date', [$sdate, $edate])
            ->where('user_id', $referrerId)
            ->select([
                DB::raw("date AS reported_date"),
                DB::raw("SUM(referral_commission) AS total_commission")
            ])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('reported_date');
            
        $days = collect(CarbonPeriod::create($sdate, $edate))->map(function ($i) use ($data) {
            $date = $i->format('Y-m-d');
            return [
                'reported_date' => $date,
                'total_commission' => $data[$date]->total_commission ?? "0.0000000000",
            ];
        });


        return $days;

    }

    public function referrerClientLevels($request) {
        return ReferrerClientLevel::all();
    }

    public function referrerClientLevel($level, $request) {
        return ReferrerClientLevel::find($level);
    }

    public function setReferrerLevel($level, $validated) {
        $old = ReferrerClientLevel::find($level);

        $changes = collect([
            'trade_min' => [$old->trade_min, $validated['trade_min']],
            'volume'    => [$old->volume,    $validated['volume']],
            'rate'      => [$old->rate,      $validated['rate']],
            'label'     => [$old->label,     $validated['label']],
        ]);

        $hasChanged = $changes->filter(fn ($pair) => $pair[0] != $pair[1])->isNotEmpty();
        if(!$hasChanged) throw new HttpException(422, 'Not has changed data');

        ReferrerClientLevelLog::create([
            'level'             => $level,
            'trade_min'         => $validated['trade_min'],
            'trade_min_before'  => $old->trade_min,
            'volume'            => $validated['volume'],
            'volume_before'     => $old->volume,
            'rate'              => $validated['rate'],
            'rate_before'       => $old->rate,
            'label'             => $validated['label'],
            'label_before'      => $old->label,
            'actor'             => 'admin:' . auth()->id(),
            'logged_at'         => Utils::currentMilliseconds(),
        ]);

        return ReferrerClientLevel::updateOrCreate(
            ['level' => $level],
            $validated
        );
    }

}
