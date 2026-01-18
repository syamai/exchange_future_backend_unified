<?php

namespace App\Http\Services;

use App\Models\AffiliateTrees;
use App\Models\CompleteTransaction;
use App\Models\ReportReferralCommissionRanking;
use App\Models\ReportTransaction;
use App\Models\User;
use App\Models\UserSamsubKyc;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AdminReferralDashboardService
{
    /**
     * Get trade volume statistics
     *
     * @param array $filters
     * @return array
     */
    public function getTradeVolumeStatistics(array $filters): array
    {
        $period = $filters['period'] ?? '7d';
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $type = $filters['type'] ?? null;

        // Calculate date range based on period
        if (!$fromDate || !$toDate) {
            $dateRange = $this->calculateDateRange($period);
            $fromDate = $dateRange['from'];
            $toDate = $dateRange['to'];
        }

        // Ensure toDate includes the full day
        $fromDate = Carbon::parse($fromDate)->startOfDay()->format('Y-m-d H:i:s');
        $toDate = Carbon::parse($toDate)->endOfDay()->format('Y-m-d H:i:s');

        // Get current period statistics
        $currentStats = $this->getPeriodStatistics($fromDate, $toDate);

        // Get previous period statistics for comparison
        $previousDateRange = $this->calculateDateRangePrevious($fromDate, $toDate, $period);
        $previousStats = $this->getPeriodStatistics($previousDateRange['from'], $previousDateRange['to']);

        // Calculate comparison percentages
        $comparison = [
            'total' => 0,
            'spot' => 0,
            'future' => 0
        ];
        if (!isset($filters['from_date']) && !isset($filters['to_date'])) {
            $comparison = [
                'total' => $this->calculateComparisonPercentage($currentStats['total'], $previousStats['total']),
                'spot' => $this->calculateComparisonPercentage($currentStats['spot'], $previousStats['spot']),
                'future' => $this->calculateComparisonPercentage($currentStats['future'], $previousStats['future'])
            ];
        }

        // Get admin timezone
        $adminTimezone = $this->getAdminTimezone();

        // Get daily data for chart
        $dailyData = $this->getDailyData($fromDate, $toDate, $previousDateRange['from'], $previousDateRange['to'], $period, $type, $adminTimezone);

        return [
            'statistics' => [
                'total' => [
                    'volume' => number_format($currentStats['total'], 8),
                    'comparison' => $comparison['total']
                ],
                'spot' => [
                    'volume' => number_format($currentStats['spot'], 8),
                    'comparison' => $comparison['spot']
                ],
                'future' => [
                    'volume' => number_format($currentStats['future'], 8),
                    'comparison' => $comparison['future']
                ]
            ],
            'chart_data' => $dailyData
        ];
    }

    /**
     * Calculate date range based on period
     *
     * @param string $period
     * @return array
     */
    private function calculateDateRange(string $period): array
    {
        $fromDate = match ($period) {
            'today' => Carbon::today(),
            'this_week' => Carbon::now()->startOfWeek(), // Monday
            'this_month' => Carbon::now()->startOfMonth(),
            'this_year' => Carbon::now()->startOfYear(),
            'last_year' => Carbon::now()->subYear()->startOfYear(),
            default => Carbon::now()->startOfWeek(),
        };

        $toDate = match ($period) {
            'today' => Carbon::today(),
            'this_week' => Carbon::now()->endOfWeek(), // Sunday
            'this_month' => Carbon::now()->endOfMonth(),
            'this_year' => Carbon::now()->endOfYear(),
            'last_year' => Carbon::now()->subYear()->endOfYear(),
            default => Carbon::now()->endOfWeek(),
        };

        return [
            'from' => $fromDate->format('Y-m-d H:i:s'),
            'to' => $toDate->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Calculate date range based on period previous
     *
     * @param string $fromDate
     * @param string $toDate
     * @param string $period
     * @return array
     */
    private function calculateDateRangePrevious(string $fromDate, string $toDate, string $period): array
    {
        $from = Carbon::parse($fromDate);
        $to = Carbon::parse($toDate);
        
        // Tính số ngày của current period
        $daysDiff = $from->diffInDays($to);
        
        $fromDate = match ($period) {
            'today' => $from->copy()->subDay()->startOfDay(),
            'this_week' => $from->copy()->subWeek()->startOfWeek(), // Monday
            'this_month' => $from->copy()->subMonthNoOverflow()->startOfMonth(),
            'this_year', 'last_year' => $from->copy()->subYear()->startOfYear(),
            default => $from->copy()->subDays($daysDiff + 1), // Custom date range: lùi về trước đúng số ngày
        };

        $toDate = match ($period) {
            'today' => $to->copy()->subDay()->endOfDay(),
            'this_week' => $to->copy()->subWeek()->endOfWeek(), // Sunday
            'this_month' => $to->copy()->subMonthNoOverflow()->endOfMonth(),
            'this_year', 'last_year' => $to->copy()->subYear()->endOfYear(),
            default => $from->copy()->subDay()->endOfDay(), // Custom date range: kết thúc tại ngày trước current start
        };

        return [
            'from' => $fromDate->format('Y-m-d H:i:s'),
            'to' => $toDate->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Get statistics for a specific period
     *
     * @param string $fromDate
     * @param string $toDate
     * @return array
     */
    private function getPeriodStatistics(string $fromDate, string $toDate): array
    {
        $query = CompleteTransaction::query()
            ->join('affiliate_trees', 'complete_transactions.user_id', '=', 'affiliate_trees.user_id')
            ->where('affiliate_trees.level', 1)
            ->whereBetween('complete_transactions.created_at', [$fromDate, $toDate]);

        $total = $query->sum('amount_usdt');
        $spot = (clone $query)->where('type', 'spot')->sum('amount_usdt');
        $future = (clone $query)->where('type', 'future')->sum('amount_usdt');

        return [
            'total' => $total,
            'spot' => $spot,
            'future' => $future
        ];
    }

    /**
     * Calculate comparison percentage
     *
     * @param float $current
     * @param float $previous
     * @return float
     */
    private function calculateComparisonPercentage(float $current, float $previous): float
    {
        if ($previous == 0) {
            // return 0;
            return $current > 0 ? 100 : 0;
        }
        return (($current - $previous) / $previous) * 100;
    }

    /**
     * Get daily data for chart
     *
     * @param string $fromDate
     * @param string $toDate
     * @param string $prevFromDate
     * @param string $prevToDate
     * @param string $period
     * @param string|null $type
     * @param string $adminTimezone
     * @return array
     */
    private function getDailyData(string $fromDate, string $toDate, string $prevFromDate, string $prevToDate, string $period, ?string $type = null, string $adminTimezone = 'UTC'): array
    {
        // Check if fromDate and toDate are the same day
        // If same day, display chart data by hours (like 'today' period)
        $fromDateOnly = Carbon::parse($fromDate)->format('Y-m-d');
        $toDateOnly = Carbon::parse($toDate)->format('Y-m-d');
        $isSameDay = $fromDateOnly === $toDateOnly;

        // Override period to display by hours if same day selected
        $effectivePeriod = $isSameDay ? 'today' : $period;
        // Get current period data (UTC)
        $currentDataUTC = CompleteTransaction::query()
            ->join('affiliate_trees', 'complete_transactions.user_id', '=', 'affiliate_trees.user_id')
            ->select([
                'complete_transactions.created_at',
                DB::raw('COALESCE(SUM(amount_usdt), 0) as volume')
            ])
            ->where('affiliate_trees.level', 1)
            ->whereBetween('complete_transactions.created_at', [$fromDate, $toDate])
            ->when($type, function ($query) use ($type) {
                return $query->where('complete_transactions.type', $type);
            })
            ->groupBy('complete_transactions.created_at')
            ->orderBy('complete_transactions.created_at')
            ->get();

        // Get previous period data (UTC)
        $previousDataUTC = CompleteTransaction::query()
            ->join('affiliate_trees', 'complete_transactions.user_id', '=', 'affiliate_trees.user_id')
            ->select([
                'complete_transactions.created_at',
                DB::raw('COALESCE(SUM(amount_usdt), 0) as volume')
            ])
            ->where('affiliate_trees.level', 1)
            ->whereBetween('complete_transactions.created_at', [$prevFromDate, $prevToDate])
            ->when($type, function ($query) use ($type) {
                return $query->where('complete_transactions.type', $type);
            })
            ->groupBy('complete_transactions.created_at')
            ->orderBy('complete_transactions.created_at')
            ->get();

        // Convert UTC data to admin timezone
        $currentData = collect();
        foreach ($currentDataUTC as $item) {
            $adminTime = Carbon::parse($item->created_at)->setTimezone($adminTimezone);

            switch ($effectivePeriod) {
                case 'today':
                    $hour = $adminTime->hour; // start from 00:00
                    if ($hour > 23) $hour = 23; // Handle overflow
                    if (!isset($currentData[$hour])) {
                        $currentData[$hour] = (object)['volume' => 0];
                    }
                    $currentData[$hour]->volume += $item->volume;
                    break;

                case 'this_week':
                case 'this_month':
                    $date = $adminTime->format('Y-m-d');
                    if (!isset($currentData[$date])) {
                        $currentData[$date] = (object)['volume' => 0];
                    }
                    $currentData[$date]->volume += $item->volume;
                    break;

                case 'this_year':
                case 'last_year':
                    $month = $adminTime->format('Y-m');
                    if (!isset($currentData[$month])) {
                        $currentData[$month] = (object)['volume' => 0];
                    }
                    $currentData[$month]->volume += $item->volume;
                    break;

                default:
                    // Default case - by date
                    $date = $adminTime->format('Y-m-d');
                    if (!isset($currentData[$date])) {
                        $currentData[$date] = (object)['volume' => 0];
                    }
                    $currentData[$date]->volume += $item->volume;
                    break;
            }
        }

        $previousData = collect();
        foreach ($previousDataUTC as $item) {
            $adminTime = Carbon::parse($item->created_at)->setTimezone($adminTimezone);

            switch ($effectivePeriod) {
                case 'today':
                    $hour = $adminTime->hour; // start from 00:00
                    if ($hour > 23) $hour = 23; // Handle overflow
                    if (!isset($previousData[$hour])) {
                        $previousData[$hour] = (object)['volume' => 0];
                    }
                    $previousData[$hour]->volume += $item->volume;
                    break;

                case 'this_week':
                case 'this_month':
                    $date = $adminTime->format('Y-m-d');
                    if (!isset($previousData[$date])) {
                        $previousData[$date] = (object)['volume' => 0];
                    }
                    $previousData[$date]->volume += $item->volume;
                    break;

                case 'this_year':
                case 'last_year':
                    $month = $adminTime->format('Y-m');
                    if (!isset($previousData[$month])) {
                        $previousData[$month] = (object)['volume' => 0];
                    }
                    $previousData[$month]->volume += $item->volume;
                    break;

                default:
                    // Default case - by date
                    $date = $adminTime->format('Y-m-d');
                    if (!isset($previousData[$date])) {
                        $previousData[$date] = (object)['volume' => 0];
                    }
                    $previousData[$date]->volume += $item->volume;
                    break;
            }
        }

        // Generate all time periods based on period
        $currentPeriodData = collect();
        $previousPeriodData = collect();

        switch ($effectivePeriod) {
            case 'today':
                // Generate hours from 0:00 to 23:00
                for ($hour = 0; $hour < 24; $hour++) {
                    $currentPeriodData->push([
                        'date_time' => sprintf('%02d:00', $hour),
                        'volume' => number_format($currentData[$hour]->volume ?? 0, 8)
                    ]);
                    $previousPeriodData->push([
                        'date_time' => sprintf('%02d:00', $hour),
                        'volume' => number_format($previousData[$hour]->volume ?? 0, 8)
                    ]);
                }
                break;

            case 'this_week':
            case 'this_month':
                // Generate all dates in current range based on admin timezone
                $start = Carbon::parse($fromDate)->setTimezone($adminTimezone);
                $end = Carbon::parse($toDate)->setTimezone($adminTimezone);

                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $dateStr = $date->format('Y-m-d');
                    $currentPeriodData->push([
                        'date_time' => $dateStr,
                        'volume' => number_format($currentData[$dateStr]->volume ?? 0, 8)
                    ]);
                }
                // Generate all dates for previous period based on admin timezone
                $prevStart = Carbon::parse($prevFromDate)->setTimezone($adminTimezone);
                $prevEnd = Carbon::parse($prevToDate)->setTimezone($adminTimezone);

                for ($date = $prevStart->copy(); $date->lte($prevEnd); $date->addDay()) {
                    $dateStr = $date->format('Y-m-d');
                    $previousPeriodData->push([
                        'date_time' => $dateStr,
                        'volume' => number_format($previousData[$dateStr]->volume ?? 0, 8)
                    ]);
                }
                break;

            case 'this_year':
            case 'last_year':
                // Generate all months (1-12) for the target year to avoid timezone overflow
                $targetYear = Carbon::parse($fromDate)->year;
                $prevTargetYear = Carbon::parse($prevFromDate)->year;

                // Generate 12 months for current year
                for ($month = 1; $month <= 12; $month++) {
                    $dateStr = sprintf('%d-%02d', $targetYear, $month);
                    $currentPeriodData->push([
                        'date_time' => $dateStr,
                        'volume' => number_format($currentData[$dateStr]->volume ?? 0, 8)
                    ]);
                }

                // Generate 12 months for previous year
                for ($month = 1; $month <= 12; $month++) {
                    $dateStr = sprintf('%d-%02d', $prevTargetYear, $month);
                    $previousPeriodData->push([
                        'date_time' => $dateStr,
                        'volume' => number_format($previousData[$dateStr]->volume ?? 0, 8)
                    ]);
                }
                break;

            default:
                // Default case - by date based on admin timezone
                $start = Carbon::parse($fromDate)->setTimezone($adminTimezone);
                $end = Carbon::parse($toDate)->setTimezone($adminTimezone);

                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $dateStr = $date->format('Y-m-d');
                    $currentPeriodData->push([
                        'date_time' => $dateStr,
                        'volume' => number_format($currentData[$dateStr]->volume ?? 0, 8)
                    ]);
                }
                // Generate all dates for previous period based on admin timezone
                $prevStart = Carbon::parse($prevFromDate)->setTimezone($adminTimezone);
                $prevEnd = Carbon::parse($prevToDate)->setTimezone($adminTimezone);

                for ($date = $prevStart->copy(); $date->lte($prevEnd); $date->addDay()) {
                    $dateStr = $date->format('Y-m-d');
                    $previousPeriodData->push([
                        'date_time' => $dateStr,
                        'volume' => number_format($previousData[$dateStr]->volume ?? 0, 8)
                    ]);
                }
                break;
        }

        return [
            'current' => $currentPeriodData,
            'previous' => $previousPeriodData,
            'timezone' => $adminTimezone // Add timezone info for frontend
        ];
    }

    /**
     * Get timezone from IP address
     *
     * @param string|null $ip
     * @return string
     */
    private function getTimezoneFromIP(?string $ip = null): string
    {
        if (!$ip) {
            $ip = request()->ip();
        }

        // Fallback timezone if IP detection fails
        $defaultTimezone = 'UTC';

        try {
            // Free API service
            $response = file_get_contents("http://ip-api.com/json/{$ip}");
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['timezone'])) {
                    return $data['timezone'];
                }
            }
        } catch (\Exception $e) {
            \Log::warning("Failed to get timezone from IP: {$ip}", ['error' => $e->getMessage()]);
        }

        return $defaultTimezone;
    }

    /**
     * Get timezone from Header or IP
     *
     * @return string
     */
    private function getAdminTimezone(): string
    {
        // Ưu tiên 1: Lấy từ request header (nếu frontend gửi)
        if (request()->hasHeader('X-Timezone')) {
            return request()->header('X-Timezone');
        }

        // Ưu tiên 2: Detect từ IP
        return $this->getTimezoneFromIP();
    }

    /**
     * Get referrers summary statistics
     *
     * @param array $filters
     * @return array
     */
    public function getReferrersSummary(array $filters): array
    {
        $period = $filters['period'] ?? 'today';
        $adminTimezone = $this->getAdminTimezone();

        // Calculate date range based on period
        $dateRange = $this->calculateDateRange($period);
        $fromDate = Carbon::parse($dateRange['from'])->startOfDay()->format('Y-m-d H:i:s');
        $toDate = Carbon::parse($dateRange['to'])->endOfDay()->format('Y-m-d H:i:s');

        // Get total referrers (unique referrer_id)
        $totalReferrers = DB::table('affiliate_trees')
            ->where('level', 1)
            ->select('referrer_id')
            ->groupBy('referrer_id')
            ->get()
            ->count();

        // Get today's new referrers (referrer_id with first referral today)
        $todayNewReferrers = DB::table('affiliate_trees as at1')
            ->where('at1.level', 1)
            ->whereDate('at1.created_at', Carbon::today())
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('affiliate_trees as at2')
                    ->whereColumn('at2.referrer_id', 'at1.referrer_id')
                    ->where('at2.level', 1)
                    ->where('at2.created_at', '<', Carbon::today()->startOfDay());
            })
            ->select('at1.referrer_id')
            ->distinct()
            ->count();

        // Get daily referral signups based on period (by UTC)
        $signupsData = DB::table('affiliate_trees')
            ->where('level', 1)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->when($period === 'today', function ($query) {
                return $query->select(
                    'created_at',
                    DB::raw('COUNT(DISTINCT user_id) as count')
                );
            })
            ->when(in_array($period, ['this_week', 'this_month']), function ($query) {
                return $query->select(
                    'created_at',
                    DB::raw('COUNT(DISTINCT user_id) as count')
                );
            })
            ->when(in_array($period, ['this_year', 'last_year']), function ($query) {
                return $query->select(
                    'created_at',
                    DB::raw('COUNT(DISTINCT user_id) as count')
                );
            })
            ->groupBy('user_id')
            ->get();

        // Convert UTC data to admin timezone
        $convertedData = collect();
        foreach ($signupsData as $item) {
            $adminTime = Carbon::parse($item->created_at)->setTimezone($adminTimezone);

            switch ($period) {
                case 'today':
                    $hour = $adminTime->hour;
                    if (!isset($convertedData[$hour])) {
                        $convertedData[$hour] = 0;
                    }
                    $convertedData[$hour] += $item->count;
                    break;

                case 'this_week':
                case 'this_month':
                    $date = $adminTime->format('Y-m-d');
                    if (!isset($convertedData[$date])) {
                        $convertedData[$date] = 0;
                    }
                    $convertedData[$date] += $item->count;
                    break;

                case 'this_year':
                case 'last_year':
                    $month = $adminTime->format('Y-m');
                    if (!isset($convertedData[$month])) {
                        $convertedData[$month] = 0;
                    }
                    $convertedData[$month] += $item->count;
                    break;
            }
        }

        // Generate all time periods
        $dailySignups = collect();

        switch ($period) {
            case 'today':
                // Generate all hours (0-23) based on admin timezone
                for ($hour = 0; $hour < 24; $hour++) {
                    $dailySignups->push([
                        'date_time' => sprintf('%02d:00', $hour),
                        'count' => $convertedData[$hour] ?? 0
                    ]);
                }
                break;
            case 'this_week':
                // Generate all dates in range
                $start = Carbon::parse($fromDate)->setTimezone($adminTimezone);
                $end = Carbon::parse($toDate)->setTimezone($adminTimezone);

                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $dateStr = $date->format('Y-m-d');
                    $dailySignups->push([
                        'date_time' => $dateStr,
                        'display_date' => $date->format('D'),
                        'count' => $convertedData[$dateStr] ?? 0
                    ]);
                }
                break;
            case 'this_month':
                // Generate all dates in range
                $start = Carbon::parse($fromDate)->setTimezone($adminTimezone);
                $end = Carbon::parse($toDate)->setTimezone($adminTimezone);

                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $dateStr = $date->format('Y-m-d');
                    $dailySignups->push([
                        'date_time' => $dateStr,
                        'display_date' => $date->format('Y-m-d'),
                        'count' => $convertedData[$dateStr] ?? 0
                    ]);
                }
                break;
            case 'this_year':
            case 'last_year':
                // Generate all months (Jan - Dec)
                $currentYear = Carbon::now()->year;
                $targetYear = $period === 'last_year' ? $currentYear - 1 : $currentYear;

                for ($month = 1; $month <= 12; $month++) {
                    $date = Carbon::create($targetYear, $month, 1)->setTimezone($adminTimezone);
                    $monthStr = $date->format('Y-m');
                    $dailySignups->push([
                        'date_time' => $monthStr,
                        'display_date' => $date->format('F'),
                        'count' => $convertedData[$monthStr] ?? 0
                    ]);
                }
                break;
        }

        return [
            'total_referrers' => $totalReferrers,
            'today_new_referrers' => $todayNewReferrers,
            'daily_signups' => $dailySignups,
            'timezone' => $adminTimezone // Thêm timezone vào response để frontend biết
        ];
    }

    /**
     * Get referrals summary statistics
     *
     * @return array
     */
    public function getReferralsSummary(): array
    {
        // Get total referrals (unique user_id)
        $totalReferrals = DB::table('affiliate_trees')
            ->where('level', 1)
            ->select('user_id')
            ->groupBy('user_id')
            ->get()
            ->count();

        // Get today's new referrals (unique user_id)
        $todayNewReferrals = DB::table('affiliate_trees')
            ->where('level', 1)
            ->whereDate('created_at', Carbon::today())
            ->select('user_id')
            ->groupBy('user_id')
            ->get()
            ->count();

        // Get valid referrals (users with at least 1 transaction in previous month AND KYC verified)
        $validReferrals = DB::table('affiliate_trees as at')
            ->join('complete_transactions as ct', 'at.user_id', '=', 'ct.user_id')
            ->join('user_security_settings as uss', 'at.user_id', '=', 'uss.id')
            ->where('at.level', 1)
            ->whereBetween('ct.created_at', [Carbon::now()->subMonthNoOverflow()->startOfMonth(), Carbon::now()->endOfDay()])
            ->where('uss.identity_verified', 1)
            ->select('at.user_id')
            ->groupBy('at.user_id')
            ->get()
            ->count();

        $invalidReferrals = $totalReferrals - $validReferrals;
        $validPercentage = $totalReferrals > 0 ? ($validReferrals / $totalReferrals) * 100 : 0;
        $invalidPercentage = $totalReferrals > 0 ? ($invalidReferrals / $totalReferrals) * 100 : 0;

        return [
            'total_referrals' => $totalReferrals,
            'today_new_referrals' => $todayNewReferrals,
            'valid_referral_count' => [
                'total' => $totalReferrals,
                'valid' => $validReferrals,
                'invalid' => $invalidReferrals,
                'valid_percentage' => round($validPercentage, 2),
                'invalid_percentage' => round($invalidPercentage, 2)
            ]
        ];
    }

    public function overallReferralConversionRate($params) {
        $sdateRaw = Arr::get($params, 'sdate');
        $edateRaw = Arr::get($params, 'edate');

        $sdate = $sdateRaw ? Carbon::createFromTimestamp($sdateRaw)->startOfDay()->toDateTimeString() : null;
        $edate = $edateRaw ? Carbon::createFromTimestamp($edateRaw)->endOfDay()->toDateTimeString() : null;
        // $startOfLastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth()->startOfDay()->toDateString();
        // $endOfLastMonth = Carbon::now()->subMonthNoOverflow()->endOfMonth()->endOfDay()->toDateString();
        
        // dd($startOfLastMonth, $endOfLastMonth);
        // dd(Carbon::createFromDate('2024-08-15')->timestamp);

        $data = collect();
        $usersRegister = User::query()
            ->where('type', '<>', 'bot')
            ->where('status', 'active')
            ->when($sdate && $edate, function ($q) use ($sdate, $edate) {
                $q->where(function ($sub) use ($sdate, $edate) {
                    $sub->whereBetween('registered_at', [$sdate, $edate])
                        ->orWhereBetween('created_at', [$sdate, $edate]);
                });
            });

        $referrals = AffiliateTrees::whereHas('userDown', function ($q) use ($sdate, $edate) {
            $q->where('status', 'active')
            ->where('type', '<>', 'bot')
            ->whereNotNull('referrer_id')
            ->when($sdate && $edate, function ($q) use ($sdate, $edate) {
                $q->where(function ($sub) use ($sdate, $edate) {
                    $sub->whereBetween('registered_at', [$sdate, $edate])
                        ->orWhereBetween('created_at', [$sdate, $edate]);
                });
            })
            ->whereHas('userSamsubKYC', function ($q) {
                $q->where('status', UserSamsubKyc::VERIFIED_STATUS);
            });
        })
        ->where('level', 1)
        ->whereExists(function ($query) //use ($startOfLastMonth, $endOfLastMonth) 
        {
            $query->select(DB::raw(1))
                ->from('report_transactions as r')
                ->whereColumn('r.user_id', 'affiliate_trees.user_id');
                // ->whereBetween('r.date', [$startOfLastMonth, $endOfLastMonth]);
        })
        ->select('user_id')
        ->distinct()
        ->count();

        $users = (clone $usersRegister)->count();
        
        $ref = (clone $usersRegister)
        ->where('referrer_id', '<>', null)
        ->count();

        $refKyc = (clone $usersRegister)
            ->where('referrer_id', '<>', null)
            ->whereHas('userSamsubKYC', fn($q) =>
                $q->where('status', UserSamsubKyc::VERIFIED_STATUS)
            )->count();

        $rateLayer2 = ($users > 0) ? round(($ref/ $users)* 100 , 2) : 0;
        $rateLayer3 = ($users > 0) ? round(($refKyc/ $users)*100, 2) : 0;
        $rateLayer4 = ($users > 0) ? round(($referrals/ $users)*100, 2) : 0;

        $data->put('layer1', ['total' => $users, 'rate' => 100]);
        $data->put('layer2', ['total' => $ref, 'rate' => $rateLayer2]);
        $data->put('layer3', ['total' => $refKyc, 'rate' => $rateLayer3]);
        $data->put('layer4', ['total' => $referrals, 'rate' => $rateLayer4]);

        return $data;
    }

    public function topPerformers($params, $request) {
        $sdateRaw = Arr::get($params, 'sdate');
        $edateRaw = Arr::get($params, 'edate');

        $sdate = Carbon::createFromTimestamp($sdateRaw)->toDateString();
        $edate = Carbon::createFromTimestamp($edateRaw)->toDateString();

        $list = AffiliateTrees::whereHas('userUp')
            ->where('level', 1)
            ->select('referrer_id', DB::raw('COUNT(user_id) as referral_count'))
            ->groupBy('referrer_id')
            ->orderByDesc('referral_count')
            ->with('userUp:id,email')
            ->get();

        $mapped = $list->map(function ($item) use ($sdate, $edate) {
            // Tổng hoa hồng & phí của chính referrer
            $commissionRef = ReportTransaction::query()
                ->whereBetween('date', [$sdate, $edate])
                ->where('user_id', $item->referrer_id)
                ->where('volume', '=', 0)
                ->selectRaw('SUM(referral_commission) as referral_commission, SUM(commission) as commission_total')
                ->first();

            // Danh sách user được ref
            $referrals = AffiliateTrees::query()
                ->where('level', 1)
                ->where('referrer_id', $item->referrer_id)
                ->pluck('user_id');

            // Tổng commission của referrals
            $commissionReferrals = ReportTransaction::query()
                ->whereBetween('date', [$sdate, $edate])
                ->whereIn('user_id', $referrals)
                ->where('volume', '>', 0)
                ->select(DB::raw("SUM(commission) as commission_total, SUM(fee) as fee_total"))
                ->first();

            // Tính profit
            $commission_referrer = BigNumber::new($commissionRef->commission_total ?? 0);
            $commission_referrals = BigNumber::new($commissionRef->referral_commission ?? 0);
            
            $fee_referrals = BigNumber::new($commissionReferrals->fee_total ?? 0);

            $commission_total = BigNumber::new($commission_referrer)->add($commission_referrals);

            $profit = $fee_referrals->sub($commission_total);
            $rank = ReportReferralCommissionRanking::rank($item->referrer_id);

            return [
                'referrer_id'     => $item->referrer_id,
                'rank' => $rank,
                'referrals'  => $item->referral_count,
                'email'           => $item->userUp->email ?? null,
                'commission'      => $commission_referrals->toString(),
                'profit'          => $profit->toString(),
            ];
        })
        ->sortByDesc('referrals')
        // ->sortByDesc('rank')
        ->sortByDesc('commission')
        ->sortByDesc('profit');
        
        // Get page and per_page from query
        $perPage = (int) $request->query('limit', 10);
        $page = (int) $request->query('page', 1);

        // Slice the collection manually
        $currentItems = $mapped->slice(($page - 1) * $perPage, $perPage)->values();

        // Create paginator
        $paginator = new LengthAwarePaginator(
            $currentItems,
            $mapped->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Return API format
        return [
            'list' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'path' => $paginator->path(),
                'params' => $request->query()
            ]
        ];
    }
}
