<?php

namespace App;

use App\Console\Commands\Handler;
use App\Http\Services\MasterdataService;
use App\Http\Services\PriceService;
use App\Http\Services\UserService;
use App\Models\UserSecuritySetting;
use App\Utils\BigNumber;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;
use Exception;
use ErrorException;
use DB;

class Utils
{
    public static function getClientIp($request = null)
    {
        if (getenv('HTTP_X_FORWARDED_FOR')) {
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('X-Forwarded-For')) {
            $ipaddress = getenv('X-Forwarded-For');
        } elseif (getenv('CF-Connecting-IP')) {
            $ipaddress = getenv('CF-Connecting-IP');
        } elseif (getenv('HTTP_CLIENT_IP')) {
            $ipaddress = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED')) {
            $ipaddress = getenv('HTTP_X_FORWARDED');
        } elseif (getenv('HTTP_FORWARDED_FOR')) {
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        } elseif (getenv('HTTP_FORWARDED')) {
            $ipaddress = getenv('HTTP_FORWARDED');
        } elseif (getenv('REMOTE_ADDR')) {
            $ipaddress = getenv('REMOTE_ADDR');
        } else {
            $ipaddress = null;
        }
        if (!$ipaddress && $request) {
            return $request->ip();
        }
        return $ipaddress;
    }

    public static function getIp()
    {
        foreach (array(
                     'HTTP_CLIENT_IP',
                     'HTTP_X_FORWARDED_FOR',
                     'HTTP_X_FORWARDED',
                     'HTTP_X_CLUSTER_CLIENT_IP',
                     'HTTP_FORWARDED_FOR',
                     'HTTP_FORWARDED',
                     'REMOTE_ADDR'
                 ) as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip); // just to be safe
                    if (filter_var($ip, FILTER_VALIDATE_IP,
                            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
    }

    public static function makeQrCodeApikey($text, $filename)
    {
        $base64_image = base64_encode(QrCode::size(240)->margin(0)->generate($text));
        $data = substr($base64_image, strpos($base64_image, ',') + 1);

        $data = base64_decode($data);
        $path = Storage::disk('s3')->put("apikey/$filename" . ".png", $data);

        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');
        $path = "https://{$bucket}.s3-{$region}.amazonaws.com/{$path}";
        return $path;
    }

    public static function makeQrCode($text)
    {
        // check if the qr code already exist then return it
        // otherwise, generate new one and return

        $exists = Storage::disk('s3')->exists("qr_codes/$text.png");
        if (!$exists) {
            $data = QrCode::format('png')->size(220)->generate((string)$text);
            Storage::disk('s3')->put("qr_codes/{$text}.png", $data);
        }

        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');

        return Utils::getPresignedUrl("https://{$bucket}.s3-{$region}.amazonaws.com/qr_codes/{$text}.png");
    }

    public static function getBannerMailTemplate($text)
    {
        try {
            Storage::disk('s3')->exists("mail/banner.png");
        } catch (Throwable $e) {
            return null;
        }

        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');

        return "https://{$bucket}.s3-{$region}.amazonaws.com/mail/banner.png";
    }

    public static function getImageUrl($path)
    {
        try {
            Storage::disk('s3')->exists($path);
        } catch (Throwable $e) {
            return null;
        }

        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');

        return "https://{$bucket}.s3-{$region}.amazonaws.com/". $path;
    }

    public static function getFooterMailTemplate($text)
    {
        try {
            Storage::disk('s3')->exists("mail/footer.png");
        } catch (Throwable $e) {
            return null;
        }

        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');

        return "https://{$bucket}.s3-{$region}.amazonaws.com/mail/footer.png";
    }

    public static function makeQrCodeReferral(string $text): string
    {
        // check if the qr code already exist then return it
        // otherwise, generate new one and return
        $name = 'user_ref_code_' . substr($text, strpos($text, '=') + 1);
        $exists = Storage::disk('s3')->exists("qr_codes/referral/$name.png");
        if (!$exists) {
            $data = QrCode::format('png')->size(500)->generate($text);
            Storage::disk('s3')->put("qr_codes/referral/{$name}.png", $data);
        }

        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');
        return Utils::getPresignedUrl("https://{$bucket}.s3-{$region}.amazonaws.com/qr_codes/referral/{$name}.png");
    }

    public static function makeQrCodeAccountApiKey(string $text, $userId): string
    {
        $apiKey = json_decode($text, true);
        $name = 'acc_api_key_' . $userId . '_' . $apiKey['api_key'];

        $exists = Storage::disk('s3')->exists("qr_codes/account_api_key/$name.png");
        if (!$exists) {
            $data = QrCode::format('png')->size(200)->margin(0)->generate($text);
            Storage::disk('s3')->put("qr_codes/account_api_key/{$name}.png", $data);
        }

        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');

        return Utils::getPresignedUrl("https://{$bucket}.s3-{$region}.amazonaws.com/qr_codes/account_api_key/{$name}.png");
    }

    public static function checkFileKYCUserExists($userId, $imgId) {
        $name = $userId . '_' . $imgId;
        return Storage::disk('s3')->exists("kyc/samsub/{$userId}/$name.jpg");
    }

    public static function saveFileKYCUser($data, $userId, $imgId): string
    {
        $name = $userId . '_' . $imgId;

        $path = "kyc/samsub/{$userId}/$name.jpg";
        $exists = Storage::disk('s3')->exists($path);
        if (!$exists) {
            if (!$data) {
                return '';
            }
            Storage::disk('s3')->put($path, $data);
        }

        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');

        //return Utils::getPresignedUrl("https://{$bucket}.s3-{$region}.amazonaws.com/kyc/samsub/{$userId}/{$name}.jpg");
        return "https://{$bucket}.s3-{$region}.amazonaws.com/$path";
    }

    public static function isTesting(): bool
    {
        return env('APP_ENV') == Consts::ENV_TESTING;
    }

    public static function getAllCoins()
    {
    }

    public static function isEqual($a, $b): bool
    {
        return abs($a - $b) < 1e-10;
    }

    public static function todaySeoul()
    {
        return Carbon::today('Asia/Seoul');
    }

    public static function previous24hInMillis(): float|int
    {
        return Carbon::now()->subDay()->timestamp * 1000;
    }

    public static function previous5minutes($moment)
    {
        return Carbon::now()->diffInMinutes($moment) <= 5;
    }

    public static function previous3minutes($moment): bool
    {
        return Carbon::now()->diffInMinutes($moment) <= 3;
    }

    public static function previousDayInMillis($day): float|int
    {
        return Carbon::now()->subDay($day)->timestamp * 1000;
    }

    public static function currentMilliseconds(): float
    {
        return round(microtime(true) * 1000);
    }

    public static function formatUsdAmount($amount): string
    {
        return number_format(abs($amount));
    }

    public static function millisecondsToDateTime($timestamp, $timezoneOffsetInMins, $format): string
    {
        return Utils::millisecondsToCarbon($timestamp, $timezoneOffsetInMins)->format($format);
    }

    public static function millisecondsToCarbon($timestamp, $timezoneOffsetInMins): Carbon
    {
        return Carbon::createFromTimestampUTC(floor($timestamp / 1000))->subMinutes($timezoneOffsetInMins);
    }

    public static function dateTimeToMilliseconds($stringDate): float|int
    {
        $date = !empty($stringDate) ? Carbon::parse($stringDate) : Carbon::now();
        return $date->timestamp * 1000 + $date->micro;
    }

    public static function getRedisTime($redis): float|int
    {
        $data = $redis->time();
        return $data[0] * 1000 + round($data[1] / 1000);
    }

    public static function setLocale($request, $overrideUserLocale = false)
    {
        $userService = new UserService();
        $userLocale = $userService->getCurrentUserLocale();
        if ($request->has('lang')) {
            $locale = $request->input('lang');

            if (in_array($locale, Consts::SUPPORTED_LOCALES)) {
                Session::put('user.locale', $locale);
            }
        }
        if (Session::has('user.locale')) {
            $locale = Session::get('user.locale');
            if ($locale !== $userLocale && $overrideUserLocale) {
                $userService->updateOrCreateUserLocale($locale);
            }
            $userLocale = $locale;
        }
        if ($userLocale !== App::getLocale()) {
            App::setLocale($userLocale);
        }
        return $userLocale;
    }

    public static function setLocaleAdmin($request)
    {
        $userService = new UserService();
        $adminLocale = $userService->getCurrentAdminLocale();
        if ($request->has('lang')) {
            $locale = $request->input('lang');

            if (in_array($locale, Consts::SUPPORTED_LOCALES)) {
                Session::put('admin.locale', $locale);
            }
        }
        if (Session::has('admin.locale')) {
            $locale = Session::get('admin.locale');
            if ($locale !== $adminLocale) {
                $adminLocale = $userService->updateOrCreateAdminLocale($locale);
            }
        }
        if ($adminLocale !== App::getLocale()) {
            App::setLocale($adminLocale);
        }
        return $adminLocale;
    }

    public static function generateRandomString(
        $length,
        $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
    ) {
        $pieces = [];
        $max = strlen($keyspace) - 1;
        for ($i = 0; $i < $length; ++$i) {
            $pieces [] = $keyspace[random_int(0, $max)];
        }
        return implode('', $pieces);
    }

    public static function createTransactionMessage($transaction)
    {
        $amount = number_format(abs($transaction->amount));
        $text = BigNumber::new($transaction->amount)->comp(0) > 0 ? 'new_deposit' : 'new_withdrawal';
        return __(
            "admin.$text",
            [
                'amount' => $amount,
                'time' => Carbon::now(Consts::DEFAULT_TIMEZONE)->format('H:i:s')
            ],
            Consts::DEFAULT_USER_LOCALE
        );
    }

    public static function mulBigNumber($number1, $number2)
    {
        if (!$number1 || !$number2) {
            return "0";
        }
        return (new BigNumber($number1))->mul($number2)->toString();
    }

    public static function convertToBTC($amount, $coin)
    {
        $priceService = new PriceService();
        return BigNumber::new($amount)->mul($priceService->convertPriceToBTC($coin, true))->toString();
    }

    public static function convertBTCToCoin($amount, $coin)
    {
        $priceService = new PriceService();
        return BigNumber::new($amount)->div($priceService->convertPriceToBTC($coin, true))->toString();
    }

    public static function getOrderStatusV2($order)
    {
        if ($order->custom_status == 'filled') {
            return __('Filled');
        }

        if ($order->custom_status == 'canceled') {
            return __('Canceled');
        }

        return __('Partial Filled');
    }

    public static function getOrderStatus($order)
    {
        if ($order->status == 'canceled') {
            return __('Canceled');
        }

        if ($order->status == 'executed') {
            return __('Filled');
        }

        return __('Partial Filled');
    }

    public static function getTriggerConditionStatus($condition): string
    {
        if ($condition == 'le') {
            return '<=';
        }
        if ($condition == 'ge') {
            return '>=';
        }
        return '';
    }

    public static function trimFloatNumber($val)
    {
        return strpos($val, '.') !== false ? rtrim(rtrim($val, '0'), '.') : $val;
    }

    public static function tradeType($val)
    {
        if ($val == 'buy') {
            return __('Buy');
        }
        if ($val == 'sell') {
            return __('Sell');
        }
        if ($val == 'funding') {
            return __('Funding');
        }
    }

    public static function saveFileToStorage($file, $pathFolder, $prefixName = null, $visibility = null): string|null
    {
        return Storage::disk('s3')->put($pathFolder, $file);
    }

    public static function getPresignedUrl($url)
    {
        if (strpos($url, 'amazonaws.com') === false) {
            return $url;
        }

        $parsed = parse_url($url);
        if (!$parsed) {
            return '';
        }

        $file = $parsed['path'];
        if (!$file) {
            return '';
        }

        $file = substr($file, 1);
        $presignedUrl = Storage::disk('s3')->temporaryUrl($file, Carbon::now()->addMinutes(360));
        return $presignedUrl;
    }

    public static function formatVndAmount($amount)
    {
        return number_format(abs($amount));
    }

    public static function getLimitEndOfTimeInMillis()
    {
        return Carbon::now()->endOfDay()->timestamp * 1000;
    }

    public static function getTradeLimitStartOfTimeInMillis($coin, $currency)
    {
        $tradeLimit = static::getTradingLimit($coin, $currency);
        return Carbon::now()->addDay()->startOfDay()->subDays($tradeLimit->days)->timestamp * 1000;
    }

    public static function getTradingLimit($coin, $currency)
    {
        $tradingLimits = MasterdataService::getOneTable('trading_limits');
        return $tradingLimits->first(function ($value) use ($coin, $currency) {
            return $value->coin === $coin && $value->currency === $currency;
        });
    }

    public static function customPaginate($page, $data, $limit)
    {
        $offSet = ($page * $limit) - $limit;
        $itemsForCurrentPage = array_slice($data, $offSet, $limit, true);
        $result = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($data), $limit, $page);
        $result = $result->toArray();
        return $result;
    }

    public static function customSortByDesc($data, $field)
    {
        return array_values(Arr::sort($data, function ($value) use ($field) {
            return -$value[$field];
        }));
    }

    public static function customSort($data, $field)
    {
        return array_values(Arr::sort($data, function ($value) use ($field) {
            return $value[$field];
        }));
    }

    public static function customSortWithReplaceValue($data, $field, $value1, $value2)
    {
        return array_values(Arr::sort($data, function ($value) use ($field, $value1, $value2) {
            if ($value[$field] == $value1) {
                return $value2;
            }
            return $value[$field];
        }));
    }

    public static function customSortData($params, $data, $limit, $fieldDefault)
    {
        $sortType = Arr::get($params, 'sort_type', 'desc');
        $sortFile = Arr::get($params, 'sort', $fieldDefault);
        $page = @$params['page'] ?? 1;
        if ($sortType == "asc") {
            $result = Utils::customSort($data, $sortFile);
        } else {
            $result = array_reverse(Utils::customSort($data, $sortFile));
        }

        $rs = Utils::customPaginate($page, $result, $limit);
        return $rs;
    }

    public static function getWithdrawalLimitStartOfTimeInMillis($currency): float|int
    {
        $withdrawalLimit = static::getWithdrawalLimit($currency);
        return Carbon::now()->addDay()->startOfDay()->subDays($withdrawalLimit->days)->timestamp * 1000;
    }

    public static function getWithdrawalLimit($currency)
    {
        $withdrawalLimits = MasterdataService::getOneTable('withdrawal_limits');
        return $withdrawalLimits->first(function ($value) use ($currency) {
            return $value->currency === $currency && $value->security_level === 1;
        });
    }

    public static function encrypt($password): string
    {
        $salt_key = config('app.encryption_key');
        $password = $password . $salt_key;
        $enc_password = md5($password);
        return $enc_password;
    }

    public static function decryptAES($signature): array
    {
        $signature = base64_decode($signature);
        $cipher = Consts::ALGORITHM_AES_256_ECB;
        $secret = env("SECRET_KEY_ENCRYPT_PARAMS", "Dv5B733LjyY41ChSPGbt26O63HiBHqRZ");
        $option = OPENSSL_RAW_DATA;
        $decrypt = openssl_decrypt($signature, $cipher, $secret, $option);

        return (array)json_decode($decrypt);
    }

    public static function checkKycUser($userId): bool
    {
        $settings = UserSecuritySetting::where('id', $userId)->first();
        if (!$settings) {
            return false;
        }
        return $settings->identity_verified;
    }

    public static function TransferCustomTemplateForNotification($mes): array|string
    {
        $mes = str_replace("<p>", "", $mes);
        $mes = str_replace("</p>", "\n\r", $mes);
        $mes = str_replace("<ul>", "", $mes);
        $mes = str_replace("<ol>", "", $mes);
        $mes = str_replace("<li>", "      •  ", $mes);
        $mes = str_replace("</li>", "\n\r", $mes);
        $mes = str_replace("</br>", "\n\r", $mes);
        $mes = str_replace("&nbsp;", "\n\r", $mes);
        return $mes;
    }

    public static function formatCsvColumnString($value): string
    {
        return "=\"{$value}\"";
    }

    public static function yesterdaySub5MinuteInMillis()
    {
        $yesterdayFirst = Carbon::yesterday()->timestamp * 1000;
        $yesterdayLast = Carbon::today()->subMinutes(5)->timestamp * 1000;
        return [$yesterdayFirst, $yesterdayLast];
    }

    public static function makeQrCodeLogin($random, $ip, $location, $platform): object
    {
        $redis = Redis::connection(Consts::RC_ORDER_PROCESSOR);
        $loginKey = $random;
        $qrcode1 = (string)rand(000, 999);
        $qrcode2 = (string)rand(000, 999);
        $qrcode3 = (string)rand(000, 999);
        $qrcode = $qrcode1 . '-' . $qrcode2 . '-' . $qrcode3;

        $data = (object)[
            'random' => $random,
            'qrcode' => $qrcode,
            'ip' => $ip,
            'location' => $location,
            'platform' => $platform
        ];
        $redis->set($loginKey, json_encode($data), 'ex', 60);

        return $data;
    }

    public static function kafkaConsumer($topic, $handle)
    {
        $consumer = Kafka::createConsumer()
            ->subscribe($topic)
            ->withAutoCommit()
            ->withHandler(new $handle())
            ->build();

        $consumer->consume();
    }

    public static function kafkaProducer($topic, $message)
    {
        $message = new Message(
            body: $message
        );

        $producer = Kafka::publishOn($topic)->withMessage($message);
        $producer->send();
    }

    public static function kafkaProducerME($topic, $message)
    {
        $message = new Message(
            body: $message
        );

        $producer = Kafka::publishOn($topic)
            ->withConfigOptions(['metadata.broker.list' => env("KAFKA_BROKERS_ME", config('kafka.brokers'))])
            ->withMessage($message);
        $producer->send();
    }

    public static function kafkaConsumerME($topic, $handle)
    {
        $consumer = Kafka::createConsumer()
            ->withBrokers(env("KAFKA_BROKERS_ME", config('kafka.brokers')))
            ->subscribe($topic)
            ->withAutoCommit()
            ->withHandler(new $handle())
            ->build();

        $consumer->consume();
    }

    public static function convertDataKafka($message)
    {
        $data = str_replace("'", "\'", json_encode($message->getBody()));
        return json_decode($data, true);
    }

    public static function convertDataKafkaRewardFuture($message)
    {
        return $message->getBody();
    }

    public static function revokeTokensInFuture(string $accessToken): void
    {
        $futureBaseUrl = env('FUTURE_API_URL');
        $futureAccessTokenUrl = $futureBaseUrl . '/api/v1/access-token/revoke-tokens';
        $client = new Client();

        try {
            $resAccess = $client->request('PUT', $futureAccessTokenUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken
                ],
            ]);
            if ($resAccess->getStatusCode() >= 400) {
                throw new ErrorException(json_encode($resAccess->getBody()));
            }
        } catch (Exception $e) {
            Log::error("START REVOKED ALL ACCESS TOKEN TO FUTURE");
            Log::error($e);
            Log::error("END REVOKED ALL ACCESS TOKEN TO FUTURE");
        }
    }

    public static function syncAccessToken($accessToken): void
    {
        $futureBaseUrl = env('FUTURE_API_URL');
        $futureAccessTokenUrl = $futureBaseUrl . '/api/v1/access-token/v1';
        $futureUser = env('FUTURE_USER', 'sotatek_spot');
        $futurePassword = env('FUTURE_PASSWORD', '8c79VqejNhuTV98x');
        $client = new Client();

        try {

            $resAccess = $client->request('POST', $futureAccessTokenUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken
                ],
                'json' => [
                    'token' => $accessToken,
                    'futureUser' => $futureUser,
                    'futurePassword' => $futurePassword
                ],
				'timeout' => 10,
				'connect_timeout' => 10,
            ]);

            if ($resAccess->getStatusCode() >= 400) {
                throw new ErrorException(json_encode($resAccess->getBody()));
            }

        } catch (Exception $e) {
            Log::error("START SYNC ACCESS TOKEN TO FUTURE");
            Log::error($e);
            Log::error("END SYNC ACCESS TOKEN TO FUTURE");
        }
    }

    public static function getKeySymbolSpot($currency, $coin) {
        return strtoupper($coin.'_'.$currency);
    }

    public static function getStartEndByTimeFilter($timeFilter, $type = '') {
        $timeFilter = (int) $timeFilter;
        $today = Carbon::now();
        $subDayOfMonth = 0;
        $subHourOfDay = 0;
        if($type == 'chart') {
            $subDayOfMonth = 2;
            $subHourOfDay = 3;
        }
        return match ($timeFilter) {
            Consts::TIME_FILTER_TODAY => [
                'start' => $today->copy()->startOfDay()->subHours($subHourOfDay),
                'end' => $today->copy()->endOfDay(),
            ],
            Consts::TIME_FILTER_THIS_WEEK => [
                'start' => $today->copy()->startOfWeek(),
                'end' => $today->copy()->endOfWeek(),
            ],
            Consts::TIME_FILTER_THIS_MONTH => [
                'start' => $today->copy()->startOfMonth()->subDay($subDayOfMonth),
                'end' => $today->copy()->endOfMonth(),
            ],
            Consts::TIME_FILTER_LAST_MONTH => [
                'start' => $today->copy()->subMonth()->startOfMonth()->subDay($subDayOfMonth),
                'end' => $today->copy()->subMonth()->endOfMonth(),
            ],
            Consts::TIME_FILTER_THIS_YEAR => [
                'start' => $today->copy()->startOfYear(),
                'end' => $today->copy()->endOfYear(),
            ]
        };
    }
    public static function selectByTimeFilter($query, $timeFilter, $filterBy, $target, $typeColumn = 'col') {
        $timeFilter = (int) $timeFilter;
        $today = Carbon::now();

        switch ($timeFilter) {
            case Consts::TIME_FILTER_TODAY:
                $startOfDay = $today->clone()->startOfDay();
                $hour0Start = $startOfDay->clone()->subHours(3);
                $hour0 = $startOfDay;
                $query->addSelect(DB::raw("SUM(IF({$filterBy} > '{$hour0Start}' AND {$filterBy} <= '{$hour0}', {$target}, 0)) AS {$typeColumn}0"));

                $hourOfDay = 3;
                $columnNum = 1;
                while($hourOfDay <= 21) {
                    $numAdd = $hourOfDay;
                    $start = $startOfDay->clone()->addHours($numAdd - 3);
                    $end = $startOfDay->clone()->addHours($numAdd);
                    $query->addSelect(DB::raw("SUM(IF({$filterBy} > '{$start}' AND {$filterBy} <= '{$end}', {$target}, 0)) AS {$typeColumn}{$columnNum}"));
                    $hourOfDay += 3;
                    $columnNum++;
                }
                return;
            case Consts::TIME_FILTER_THIS_WEEK:
                $startOfWeek = $today->clone()->startOfWeek(Carbon::MONDAY);
                $columnNum = 0;
                for ($day = 1; $day <= 7; $day++) {
                    $start = $startOfWeek->clone()->addDay($day - 1);
                    $end = $startOfWeek->clone()->addDay($day);
                    $query->addSelect([DB::raw("SUM(IF({$filterBy} >='{$start}' AND {$filterBy} < '{$end}', {$target}, 0)) AS {$typeColumn}{$columnNum}")]);
                    $columnNum++;
                }
                return;
            case Consts::TIME_FILTER_THIS_MONTH:
            case Consts::TIME_FILTER_LAST_MONTH:
                $columnLabels = [];
                $startOfMonth = $today->clone()->startOfMonth()->endOfDay();
                if ($timeFilter == Consts::TIME_FILTER_LAST_MONTH) {$startOfMonth = $today->clone()->subMonth(1)->startOfMonth()->endOfDay();}
                $dateStart = $startOfMonth->clone()->subDay(3);
                $date1 = $startOfMonth;
                $query->addSelect(DB::raw("SUM(IF({$filterBy} > '{$dateStart}' AND {$filterBy} <= '{$date1}', {$target}, 0)) AS {$typeColumn}0"));
                $columnLabels[] = $date1->day . '/' . $date1->month; 

                $dayOfMonth = 4;
                $columnNum = 1;
                while($dayOfMonth <= 31) {
                    $numAdd = $dayOfMonth - 1;
                    $start = $startOfMonth->clone()->addDay($numAdd - 3);
                    $end = $startOfMonth->clone()->addDay($numAdd);
                    $query->addSelect(DB::raw("SUM(IF({$filterBy} > '{$start}' AND {$filterBy} <= '{$end}', {$target}, 0)) AS {$typeColumn}{$columnNum}"));
                    $columnLabels[] = $end->day . '/' . $end->month;
                    $dayOfMonth += 3;
                    $columnNum++;
                }
                return $columnLabels;
            case Consts::TIME_FILTER_THIS_YEAR:
                $startOfYear = $today->clone()->startOfYear();
                $columnNum = 0;
                for ($month = 1; $month <= 12; $month++) {
                    $start = $startOfYear->clone()->addMonth($month - 1);
                    $end = $startOfYear->clone()->addMonth($month);
                    $query->addSelect([DB::raw("SUM(IF({$filterBy} >='{$start}' AND {$filterBy} < '{$end}', {$target}, 0)) AS {$typeColumn}{$columnNum}")]);
                    $columnNum++;
                }
                return;
            default: return;
        }
        
    }

    public static function getPhone($phoneNumber, $mobileCode)
    {
        $phone = "";
        $country = MasterdataService::getOneTable('countries')->where('country_code', $mobileCode)->first();
        if ($country && $country->calling_code) {
            $phone = $country->calling_code . ltrim($phoneNumber, '0');
        }
        return $phone;
    }
}
