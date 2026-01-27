<?php

use App\Consts;
use App\Exports\ExportHelpers;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\VarDumper\VarDumper;
use Carbon\Carbon;

if (!function_exists('get_custom_email_url')) {
    function get_custom_email_url($path = ''): string
    {
        return (config('app.web').$path);
    }
}

if (!function_exists('reset_password_url')) {
	function reset_password_url($token = ''): string
	{
		return (config('app.web').Consts::AUTH_ROUTE_RESET_PASSWORD.$token);
	}
}

if (!function_exists('withdrawal_verify_url')) {
    function withdrawal_verify_url($currency = '', $token = ''): string
    {
        return (config('app.web').Consts::ROUTE_WITHDRAWAL_VERIFY.$currency.Consts::ROUTE_WITHDRAWAL_TOKEN.$token);
    }
}

if (!function_exists('confirm_email_url')) {
    function confirm_email_url($code = ''): string
    {
        return (config('app.web').Consts::AUTH_ROUTE_CONFIRM_EMAIL.$code);
    }
}

if (!function_exists('grant_device_url')) {
    function grant_device_url($code = ''): string
    {
        return (config('app.web').Consts::AUTH_ROUTE_GRANT_DEVICE.$code);
    }
}

if (!function_exists('grant_anti_phishing_url')) {
    function grant_anti_phishing_url($code = '', $type = 'create'): string
    {
        return (config('app.web').Consts::ROUTE_VERIFY_ANTI_PHISHING.$type.'/'.$code);
    }
}

if (!function_exists('login_url')) {
    function login_url(): string
    {
        return (config('app.web').Consts::AUTH_ROUTE_LOGIN);
    }
}

if (!function_exists('margin_exchange_url')) {
    function margin_exchange_url($symbol): string
    {
        return url(config('app.web').Consts::ROUTE_MARGIN_EXCHANGE.$symbol);
    }
}

if (!function_exists('base64url_encode')) {
    function base64url_encode($data = ''): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode($data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}

if (!function_exists('Amanpuri_hash')) {
    // @codingStandardsIgnoreStart
    function Amanpuri_hash($key = ''): string
    {
        $plain = $key . config('app.bit_key');
        return sha1($plain);
    }
}

if (!function_exists('Amanpuri_unique')) {
    // @codingStandardsIgnoreStart
    function Amanpuri_unique(): string
    {
        //\Illuminate\Support\Facades\Log::debug('Amanpuri_unique, ' . env('Amanpuri_ENCRYPTION_KEY'));
        $key = round(microtime(true) * 10000) . config('app.bit_key');
        return sha1($key);
    }
}

if (!function_exists('escapse_string')) {
    function escapse_string($str) : string {
        return addslashes($str);
    }
}

if (!function_exists('generate_unique_uid')) {
    function generate_unique_uid() : string {
        $uid = (string)rand(Consts::MIN_UID, Consts::MAX_UID);
        return (User::where('uid', $uid)->exists()) ? generate_unique_uid() : $uid;
    }
}

if (!function_exists('escapse_string_params')) {
    function escapse_string_params($arr): array {
        $params = [];
        foreach ($arr as $key => $value) {
            $params[$key] = escapse_string($value);
        }

        return $params;
    }
}

if (!function_exists('build_excel_file')) {
    function build_excel_file($rows, $fileName, $path, $params = []): bool
    {
        return Excel::store(
            new ExportHelpers($rows, $fileName, @$params['sheet_name'] ?? 'Order Transaction'),
            $path,
            'local',
            \Maatwebsite\Excel\Excel::CSV
        );
    }
}

if (!function_exists('df')) {
    function df(...$vars): void
    {
        if (!in_array(\PHP_SAPI, ['cli', 'phpdbg'], true) && !headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }

        foreach ($vars as $v) {
            VarDumper::dump($v);
        }
    }
}
function paginate($items, $perPage = 10, $page = null, $options = [])
{
    $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
    $items = $items instanceof Collection ? $items : Collection::make($items);
    return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
}

if(!function_exists('safeBigNumberInput')) {
    function safeBigNumberInput($val): string {
        return is_numeric($val) ? (string) $val : '0';
    }
}

if (!function_exists('detectPeriodType')) {
    function detectPeriodType(Carbon $sdate, Carbon $edate): array
    {
        $now = Carbon::now();
        $start = $sdate->copy()->startOfDay();
        $end = $edate->copy()->endOfDay();

        return [
            'is_today' => $start->isToday() && $end->isToday(),

            'is_yesterday' => $start->isSameDay($now->copy()->subDay()) &&
                              $end->isSameDay($now->copy()->subDay()),

            'is_this_week' => $start->isSameWeek($now) && $end->isSameWeek($now),

            'is_last_week' => $start->isSameWeek($now->copy()->subWeek()) &&
                              $end->isSameWeek($now->copy()->subWeek()),

            'is_this_month' => $start->isSameMonth($now) && $end->isSameMonth($now),

            'is_last_month' => $start->isSameMonth($now->copy()->subMonth()) &&
                               $end->isSameMonth($now->copy()->subMonth()),

            'is_this_year' => $start->isSameYear($now) && $end->isSameYear($now),

            'is_last_year' => $start->year === $now->copy()->subYear()->year &&
                              $end->year === $now->copy()->subYear()->year,

            'range_days' => $start->diffInDays($end) + 1,
        ];
    }
}


