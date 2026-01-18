<?php
namespace App\Http\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class ExchangeBalanceService
{
    public function getHeaderTableName()
    {
        return DB::table('take_profits')->distinct()->get(['currency']);
    }

    public function getProfitBalance($params)
    {
        Carbon::setWeekStartsAt(Carbon::MONDAY);
        Carbon::setWeekEndsAt(Carbon::SUNDAY);
        $dataCoinName = DB::table('take_profits')->distinct()->orderBy('currency')->get(['currency']);
        if (!$params->has('type') || $params['type'] === 'hours') {
            $dateTimeTemp = $params->has('date') ? Carbon::parse($params['date']) : Carbon::now();
            $dataRaw = DB::table('take_profits')
                ->select('currency', 'created_at', 'amount')
                ->selectRaw('HOUR(created_at) as hours')
                ->groupBy('hours', 'currency', 'created_at', 'amount')
                ->whereYear('created_at', $dateTimeTemp->year)
                ->whereMonth('created_at', $dateTimeTemp->month)
                ->whereDay('created_at', $dateTimeTemp->day)
                ->get();
            $dataRawLimit = DB::table('take_profits')
                ->selectRaw('HOUR(created_at) as hours')
                ->whereYear('created_at', $dateTimeTemp->year)
                ->whereMonth('created_at', $dateTimeTemp->month)
                ->whereDay('created_at', $dateTimeTemp->day)
                ->distinct()
                ->get();
            $dataReturn = new \ArrayObject();
            foreach ($dataRawLimit as $limitTime) {
                $timeStep = $limitTime->hours;
                $dataByHours = new \stdClass();
                $dataByHours->From = $this->formatStringFromHours($dateTimeTemp, $timeStep);
                $dataByHours->To = $this->formatStringToHours($dateTimeTemp, $timeStep);
                $dataCoinArr = new \stdClass();
                foreach ($dataCoinName as $dataCoin) {
                    $dataCoinArr->{$dataCoin->currency} = 0;
                    foreach ($dataRaw as $dataAmount) {
                        if ($dataAmount->hours === $timeStep && $dataAmount->currency === $dataCoin->currency) {
                            $dataCoinArr->{$dataCoin->currency} = $dataAmount->amount;
                        }
                    }
                }
                $dataTemp = (object)array_replace_recursive((array)$dataByHours, (array)$dataCoinArr);
                $dataReturn->append((array)$dataTemp);
            }
            if ($params['sort']) {
                $dataReturn = $params['sort_type'] === 'desc' ? (object) array_reverse((array) $dataReturn) : $dataReturn;
            }
            return $dataReturn;
        }
        if ($params['type'] === 'daily') {
            if ($params->has('date')) {
                $dateTimeTemp = Carbon::parse($params['date']);
                $dataRaw = DB::table('take_profits')
                    ->select(array(
                        DB::raw('DATE(created_at) as date'),
                        DB::raw('max(created_at) as max'),
                        'currency',
                        DB::raw('max(amount) as amount')
                    ))
                    ->whereYear('created_at', $dateTimeTemp->year)
                    ->whereMonth('created_at', $dateTimeTemp->month)
                    ->whereDay('created_at', $dateTimeTemp->day)
                    ->groupBy('currency', 'date')
                    ->orderBy('max')
                    ->get();
                $dataRawLimit = DB::table('take_profits')
                    ->selectRaw('DATE(created_at) as date')
                    ->distinct()
                    ->whereYear('created_at', $dateTimeTemp->year)
                    ->whereMonth('created_at', $dateTimeTemp->month)
                    ->whereDay('created_at', $dateTimeTemp->day)
                    ->latest('created_at', 'desc')
                    ->get();
            } else {
                $dataRaw = DB::table('take_profits')
                    ->select(array(
                        DB::raw('DATE(created_at) as date'),
                        DB::raw('max(created_at) as max'),
                        'currency',
                        DB::raw('max(amount) as amount')
                    ))
                    ->groupBy('currency', 'date')
                    ->orderBy('max')
                    ->get();
                $dataRawLimit = DB::table('take_profits')
                    ->selectRaw('DATE(created_at) as date')
                    ->distinct()
                    ->latest('created_at', 'desc')
                    ->get();
            }

            $dataReturn = new \ArrayObject();

            foreach ($dataRawLimit as $limitDate) {
                $dateStep = $limitDate->date;
                $dataByDateTime = new \stdClass();
                $dataByDateTime->Day = $this->formatStringFromDaily(Carbon::parse($dateStep));
                $dataCoinArr = new \stdClass();
                foreach ($dataCoinName as $dataCoin) {
                    $dataCoinArr->{$dataCoin->currency} = 0;
                    foreach ($dataRaw as $dataAmount) {
                        if ($dataAmount->date === $dateStep && $dataAmount->currency === $dataCoin->currency) {
                            $dataCoinArr->{$dataCoin->currency} = $dataAmount->amount;
                        }
                    }
                }
                $dataTemp = (object) array_replace_recursive((array) $dataByDateTime, (array) $dataCoinArr);

                $dataReturn->append((array) $dataTemp);
            }
            if ($params['sort']) {
                $dataReturn = $params['sort_type'] === 'desc' ? (object) array_reverse((array) $dataReturn) : $dataReturn;
            }
            $currentPage = Paginator::resolveCurrentPage();
            $col = collect($dataReturn);
            $perPage = $params->limit;
            $currentPageItems = $col->slice(($currentPage - 1) * $perPage, $perPage)->all();
            $items = new Paginator($currentPageItems, count($col), $perPage);
            $items->setPath($params->url());
            $items->appends($params->all());
            return $items;
        }
        if ($params['type'] === 'weekly') {
            if ($params->has('date')) {
                $dateTimeTemp = Carbon::parse($params['date']);
                $dataRaw = DB::table('take_profits')
                    ->select(array(
                        DB::raw('CONCAT(YEAR(created_at)) AS year'),
                        DB::raw('Week(created_at) AS week'),
                        DB::raw('max(created_at) as max'),
                        'currency',
                        DB::raw('max(amount) as amount')
                    ))
                    ->whereYear('created_at', $dateTimeTemp->year)
//                    ->whereBetween('created_at', [$dateTimeTemp->startOfWeek(), $dateTimeTemp->endOfWeek()])
                    ->where('created_at', '>=', $dateTimeTemp->startOfWeek()->format('Y-m-d H:i:s'))
                    ->where('created_at', '<=', $dateTimeTemp->endOfWeek()->format('Y-m-d H:i:s'))
                    ->groupBy('year')
                    ->groupBy('week')
                    ->groupBy('currency')
                    ->orderBy('max')
                    ->get();

                $dataDateLimit = DB::table('take_profits')->select(array(
                    DB::raw('CONCAT(YEAR(created_at)) AS year'),
                    DB::raw('Week(created_at) AS week'),
                    ))
                    ->whereYear('created_at', $dateTimeTemp->year)
                    ->where('created_at', '>=', $dateTimeTemp->startOfWeek()->format('Y-m-d H:i:s'))
                    ->where('created_at', '<=', $dateTimeTemp->endOfWeek()->format('Y-m-d H:i:s'))
//                    ->whereBetween('created_at', [$dateTimeTemp->startOfWeek(), $dateTimeTemp->endOfWeek()])
                    ->distinct()->get();
            } else {
                $dataRaw = DB::table('take_profits')
                    ->select(array(
                        DB::raw('CONCAT(YEAR(created_at)) AS year'),
                        DB::raw('Week(created_at) AS week'),
                        DB::raw('max(created_at) as max'),
                        'currency',
                        DB::raw('max(amount) as amount')
                    ))
                    ->groupBy('year')
                    ->groupBy('week')
                    ->groupBy('currency')
                    ->orderBy('max')
                    ->get();
                $dataDateLimit = DB::table('take_profits')->select(array(
                    DB::raw('CONCAT(YEAR(created_at)) AS year'),
                    DB::raw('Week(created_at) AS week'),
                ))->distinct()->get();
            }
            $dataReturn = new \ArrayObject();
            foreach ($dataDateLimit as $dateLimit) {
                $yearStep = $dateLimit->year;
                $weekStep = $dateLimit->week + 1;
                $dataByWeek = new \ArrayObject();
                foreach ($dataRaw as $dateByWeek) {
                    if ($dateByWeek->year === $dateLimit->year && $dateByWeek->week === $dateLimit->week) {
                        $dataByWeek->{$dateByWeek->currency} = $dateByWeek->amount;
                    }
                }
                $dataByDateTime = new \stdClass();
                $dataByDateTime->From = $this->formatStringFromWeekly($yearStep, $weekStep);
                $dataByDateTime->To   = $this->formatStringToWeekly($yearStep, $weekStep);
                foreach ($dataCoinName as $coinName) {
                    if (isset($dataByWeek->{$coinName->currency})) {
                        $dataByDateTime->{$coinName->currency} = $dataByWeek->{$coinName->currency};
                    } else {
                        $dataByDateTime->{$coinName->currency} = 0;
                    }
                }
                $dataReturn->append((array) $dataByDateTime);
            }
            if ($params['sort']) {
                $dataReturn = $params['sort_type'] === 'desc' ? (object) array_reverse((array) $dataReturn) : $dataReturn;
            }
            $currentPage = Paginator::resolveCurrentPage();
            $col = collect($dataReturn);
            $perPage = $params->limit;
            $currentPageItems = $col->slice(($currentPage - 1) * $perPage, $perPage)->all();
            $items = new Paginator($currentPageItems, count($col), $perPage);
            $items->setPath($params->url());
            $items->appends($params->all());
            return $items;
        }
        if ($params['type'] === 'monthly') {
            if ($params->has('date')) {
                $dateTimeTemp = Carbon::parse($params['date']);
                $dataRaw = DB::table('take_profits')
                ->select(array(
                    DB::raw('CONCAT(YEAR(created_at)) AS year'),
                    DB::raw('MONTH(created_at) AS month'),
                    DB::raw('max(created_at) as max'),
                    'currency',
                    DB::raw('max(amount) as amount')
                ))
                ->whereYear('created_at', $dateTimeTemp->year)
                ->whereMonth('created_at', $dateTimeTemp->month)
                ->groupBy('year')
                ->groupBy('month')
                ->groupBy('currency')
                ->orderBy('max')
                ->get();
                $dataDateLimit = DB::table('take_profits')->select(array(
                    DB::raw('CONCAT(YEAR(created_at)) AS year'),
                    DB::raw('MONTH(created_at) AS month'),
                ))->distinct()
                ->whereYear('created_at', $dateTimeTemp->year)
                ->whereMonth('created_at', $dateTimeTemp->month)
                ->get();
            } else {
                $dataRaw = DB::table('take_profits')
                ->select(array(
                    DB::raw('CONCAT(YEAR(created_at)) AS year'),
                    DB::raw('MONTH(created_at) AS month'),
                    DB::raw('max(created_at) as max'),
                    'currency',
                    DB::raw('max(amount) as amount')
                ))
                ->groupBy('year')
                ->groupBy('month')
                ->groupBy('currency')
                ->orderBy('max')
                ->get();
                $dataDateLimit = DB::table('take_profits')->select(array(
                    DB::raw('CONCAT(YEAR(created_at)) AS year'),
                    DB::raw('MONTH(created_at) AS month'),
                ))->distinct()->get();
            }

            $dataReturn = new \ArrayObject();
            foreach ($dataDateLimit as $dateLimit) {
                $yearStep = $dateLimit->year;
                $monthStep = $dateLimit->month;
                $dataByMonth = new \ArrayObject();
                foreach ($dataRaw as $dateByMonth) {
                    if ($dateByMonth->year === $dateLimit->year && $dateByMonth->month == $dateLimit->month) {
                        $dataByMonth->{$dateByMonth->currency} = $dateByMonth->amount;
                    }
                }
                $dataByDateTime = new \stdClass();
                $dataByDateTime->Month  = $this->formatStringFromMonthly($yearStep, $monthStep);
                foreach ($dataCoinName as $coinName) {
                    if (isset($dataByMonth->{$coinName->currency})) {
                        $dataByDateTime->{$coinName->currency} = $dataByMonth->{$coinName->currency};
                    } else {
                        $dataByDateTime->{$coinName->currency} = 0;
                    }
                }
                $dataReturn->append((array) $dataByDateTime);
            }
            if ($params['sort']) {
                $dataReturn = $params['sort_type'] === 'desc' ? (object) array_reverse((array) $dataReturn) : $dataReturn;
            }
            $currentPage = Paginator::resolveCurrentPage();
            $col = collect($dataReturn);
            $perPage = $params->limit;
            $currentPageItems = $col->slice(($currentPage - 1) * $perPage, $perPage)->all();
            $items = new Paginator($currentPageItems, count($col), $perPage);
            $items->setPath($params->url());
            $items->appends($params->all());
            return $items;
        }
        if ($params['type'] === 'yearly') {
            if ($params->has('date')) {
                $dateTimeTemp = $params->has('date') ? Carbon::parse($params['date']) : Carbon::now();
                $dataRaw = DB::table('take_profits')
                ->select(array(
                    DB::raw('CONCAT(YEAR(created_at)) AS year'),
                    DB::raw('max(created_at) as max'),
                    'currency',
                    DB::raw('max(amount) as amount')
                ))
                ->whereYear('created_at', $dateTimeTemp->year)
                ->groupBy('year')
                ->groupBy('currency')
                ->orderBy('max')
                ->get();
                $dataDateLimit = DB::table('take_profits')->select(array(
                    DB::raw('CONCAT(YEAR(created_at)) AS year'),
                ))->whereYear('created_at', $dateTimeTemp->year)->distinct()->get();
            } else {
                $dataRaw = DB::table('take_profits')
                ->select(array(
                    DB::raw('CONCAT(YEAR(created_at)) AS year'),
                    DB::raw('max(created_at) as max'),
                    'currency',
                    DB::raw('max(amount) as amount')
                ))
                ->groupBy('year')
                ->groupBy('currency')
                ->orderBy('max')
                ->get();
                $dataDateLimit = DB::table('take_profits')->select(array(
                    DB::raw('CONCAT(YEAR(created_at)) AS year'),
                ))->distinct()->get();
            }

            $dataReturn = new \ArrayObject();
            foreach ($dataDateLimit as $dateLimit) {
                $yearStep = $dateLimit->year;
                $dataByMonth = new \ArrayObject();
                foreach ($dataRaw as $dateByYear) {
                    if ($dateByYear->year === $dateLimit->year) {
                        $dataByMonth->{$dateByYear->currency} = $dateByYear->amount;
                    }
                }
                $dataByDateTime = new \stdClass();
                $dataByDateTime->Year = $yearStep;
                foreach ($dataCoinName as $coinName) {
                    if (isset($dataByMonth->{$coinName->currency})) {
                        $dataByDateTime->{$coinName->currency} = $dataByMonth->{$coinName->currency};
                    } else {
                        $dataByDateTime->{$coinName->currency} = 0;
                    }
                }
                $dataReturn->append((array) $dataByDateTime);
            }
            if ($params['sort']) {
                $dataReturn = $params['sort_type'] === 'desc' ? (object) array_reverse((array) $dataReturn) : $dataReturn;
            }
            $currentPage = Paginator::resolveCurrentPage();
            $col = collect($dataReturn);
            $perPage = $params->limit;
            $currentPageItems = $col->slice(($currentPage - 1) * $perPage, $perPage)->all();
            $items = new Paginator($currentPageItems, count($col), $perPage);
            $items->setPath($params->url());
            $items->appends($params->all());
            return $items;
        }
    }

    public function formatStringFromHours($params, $acronymDate)
    {
        $dateCarbon = $params;
        $dateCarbon->minute = 0;
        $dateCarbon->second = 0;
        $dateCarbon->hour = (int)$acronymDate;
        return $dateCarbon->toDateTimeString();
    }

    /**
     *  -----function get Hours string -----
     * @param $params
     * @param $acronymDate
     * @return mixed
     */
    public function formatStringToHours($params, $acronymDate)
    {
        $dateCarbon = $params;
        $dateCarbon->minute = 59;
        $dateCarbon->second = 59;
        $dateCarbon->hour = (int)$acronymDate;
        return $dateCarbon->toDateTimeString();
    }
    /**
     * ------function get Daily date time string------
     * @param $params
     * @param $acronymDate
     * @return mixed
     */
    public function formatStringFromDaily($params)
    {
        $dateCarbon = $params;
        $dateCarbon->hour = 0;
        $dateCarbon->minute = 0;
        $dateCarbon->second = 0;
        return $dateCarbon->toDateString();
    }
    public function formatStringToDaily($params)
    {
        $dateCarbon = $params;
        $dateCarbon->hour = 23;
        $dateCarbon->minute = 23;
        $dateCarbon->second = 59;
        return $dateCarbon->toDateString();
    }

    /**
     * -----function get Weekly String-------
     * @param $yearStep
     * @param $weekStep
     * @return mixed
     */
    public function formatStringFromWeekly($yearStep, $weekStep)
    {
        $dateCarbon = Carbon::now();
        $dateCarbon->setISODate($yearStep, $weekStep);
        $dateCarbon = $dateCarbon->startOfWeek();
        return $dateCarbon->toDateString();
    }
    public function formatStringToWeekly($yearStep, $weekStep)
    {
        $dateCarbon = Carbon::now();
        $dateCarbon->setISODate($yearStep, $weekStep);
        $dateCarbon = $dateCarbon->endOfWeek();
        return $dateCarbon->toDateString();
    }

    /**
     * ----function get Monthly string -------
     * @param $yearStep
     * @param $monthStep
     * @return mixed
     */
    public function formatStringFromMonthly($yearStep, $monthStep)
    {
        $dateCarbon = Carbon::now();
        $dateCarbon->day = 1;
        $dateCarbon->year($yearStep);
        $dateCarbon->month($monthStep);
        return $dateCarbon->format('Y - m');
    }
}
