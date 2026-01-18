<?php

namespace App\Http\Services;

use App\Consts;
use App\Utils;
use Monolog;
use Carbon\Carbon;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Handler\ChromePHPHandler;
 use Illuminate\Support\Facades\Cache;

class HealthCheckService
{
    protected $storagePath;
    protected $logPath;
    protected $timeStart;
    protected $timeEnd;
    protected $timeDuration;
    protected $strictMode;
    protected $initTime;
    protected $logger;
    protected $transactionId;
    protected string $functionName;
    protected string $domainName;
    protected string $facility;

    /**
     * Initial Health check services.
     *
     * @param  string  $functionName
     * @param  string  $domainName
     * @param  string  $facility
    **/

    public function __construct($functionName = null, $domainName = null, $strictMode = true, $facility = null)
    {
      // begin to log
        $this->storagePath = app()->storagePath()."/logs/healthcheck/";
        $this->functionName = $functionName ?? 'common-function';
        $this->domainName = $domainName ?? 'spot';
        $this->facility = $facility ?? 'local0';
        $this->strictMode = $strictMode ?? true;
        $this->initTime = microtime(true);
        $this->transactionId = round(microtime(true) * 1000);

        $this->initServiceStatus();
    }

    protected function preventLogger(): bool
    {
        if ($this->strictMode) {
            $cacheKey = $this->functionName."-".$this->domainName;
            if (Cache::has($cacheKey)) {
                $cacheValue = Cache::get($cacheKey);
                if (isset($cacheValue) && Utils::previous3minutes($cacheValue)) {
                    return false;
                } else {
                    $now = new Carbon(now());
                    Cache::put($cacheKey, $now, 5*60);
                    return true;
                }
            } else {
                $now = new Carbon(now());
                Cache::put($cacheKey, $now, 5*60);
            }
        } else {
            return true;
        }

        return true;
    }

    protected function initServiceStatus(): void
    {
        $domainName = $this->domainName;
        $facility = $this->facility;

        $cacheKey = $this->preventLogger();
        if ($cacheKey) {
            $logFile = env('APP_LOG', 'debug') == 'daily' ? $this->getDailyLog() : $this->getLog();
        } else {
            $logFile = $this->storagePath.'warning.log';
        }

        $this->logPath = $logFile;

        $logger = new Monolog\Logger($domainName);

        $localFormatter = new LineFormatter(null, null, false, true);
        $syslogFormatter = new LineFormatter("%channel%: %level_name%: %message% %context% %extra%", null, false, true);

        $infoHandler = new StreamHandler($logFile, Monolog\Logger::INFO);
        $infoHandler->setFormatter($localFormatter);

        $warnHandler = new SyslogHandler($domainName, $facility, Monolog\Logger::WARNING);
        $warnHandler->setFormatter($syslogFormatter);

        $chromePHPHandler = new ChromePHPHandler();

        $logger->pushHandler($warnHandler);
        $logger->pushHandler($infoHandler);
        // $logger->pushHandler($chromePHPHandler);

        $this->logger = $logger;
    }

    protected function getDailyLog(): string
    {
        $domainName = $this->domainName;
        $daily = date("-Y-m-d");
        $functionName = $this->functionName;
        if ($functionName == Consts::HEALTH_CHECK_SERVICE_MATCHING_ENGINE) {
            $domainName = Consts::HEALTH_CHECK_SERVICE_MATCHING_ENGINE;
        }

        return $this->storagePath.$functionName.$daily.'.log';
    }

    protected function getLog(): string
    {
        $domainName = $this->domainName;
        $functionName = $this->functionName;
        if ($functionName == Consts::HEALTH_CHECK_SERVICE_MATCHING_ENGINE) {
            $domainName = Consts::HEALTH_CHECK_SERVICE_MATCHING_ENGINE;
        }
        return $this->storagePath.$functionName.'.log';
    }

    public function startLog()
    {
        $this->timeStart = microtime(true);
        return $this->timeStart;
    }

    public function endLog()
    {
        if (!$this->timeStart) {
            $diff = microtime(true) - $this->initTime;
        }

        $diff = microtime(true) - $this->timeStart;
        $sec = intval($diff);
        $micro = $diff - $sec;
        $this->timeDuration = round($diff, 4);
        $logger = $this->logger;
        if (is_null($logger)) {
            return;
        }
        // $logger->info(json_encode(["transaction"=>$this->transactionId, "name"=>$this->functionName, "domain"=>$this->domainName, "value" => $this->timeDuration]));
        $cacheKey = now();
        $logger->info(join(",", [$this->transactionId, $cacheKey, $this->functionName, $this->domainName, $this->timeDuration]));
        //another way to log
        // $logger->info(null, ["name"=>$this->domainName, "value" => $this->timeDuration]);
        // $logger->warn('Duration = '.round(microtime(true), 4));

        return $this->timeEnd;
    }

    public static function initForMatchingEngine($domain, $symbol): HealthCheckService
    {
        return new HealthCheckService(
            Consts::HEALTH_CHECK_SERVICE_MATCHING_ENGINE . $domain . $symbol,
            $domain,
            false
        );
    }

    public function matchingEngine(): void
    {
        $logger = $this->logger;
        // $logger->info(json_encode(["transaction"=>$this->transactionId, "name"=>$this->functionName, "domain"=>$this->domainName, "value" => $id]));
        $logtime = round(microtime(true) * 1000);
        if (is_null($logger)) {
            return;
        }
        $logger->info(join(",", [$this->transactionId, $logtime, $this->functionName, $this->domainName]));
    }

    public function serviceIsWorking(): void
    {
        $logger = $this->logger;
        // $logger->info(json_encode(["transaction"=>$this->transactionId, "name"=>$this->functionName, "domain"=>$this->domainName, "value" => 1]));
        $logtime = time();
        if (is_null($logger)) {
            return;
        }
        $logger->info(join(",", [$this->transactionId, $logtime, $this->functionName, $this->domainName, 1]));
    }

    public function serviceIsNotWorking(): void
    {
        $logger = $this->logger;
        // $logger->info(json_encode(["transaction"=>$this->transactionId, "name"=>$this->functionName, "domain"=>$this->domainName, "value" => 0]));
        $logtime = time();
        if (is_null($logger)) {
            return;
        }
        $logger->info(join(",", [$this->transactionId, $logtime, $this->functionName, $this->domainName, 0]));
    }

    public function serviceNumberLogging($number): void
    {
        $logger = $this->logger;
        // $logger->info(json_encode(["transaction"=>$this->transactionId, "name"=>$this->functionName, "domain"=>$this->domainName, "value" => $number]));
        $logtime = time();
        if (is_null($logger)) {
            return;
        }
        $logger->info(join(",", [$this->transactionId, $logtime, $this->functionName, $this->domainName, $number]));
    }
}
