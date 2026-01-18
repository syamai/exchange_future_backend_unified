<?php

namespace App\Jobs;

use App\Consts;
use App\Events\AirdropBalanceUpdated;
use App\Events\BalanceUpdated;
use App\Events\MainBalanceUpdated;
use App\Events\SpotBalanceUpdated;
use App\Events\MarginBalanceUpdated;
use App\Events\MamBalanceUpdated;
use App\Http\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SendBalance extends RedisQueueJob
{
    private $userId;
    private $currencies;
    private $store;

    private $userService;

    /**
     * Create a new job instance.
     *
     * @param $userId
     * @param $currencies
     */
    public function __construct($data)
    {
        $json = json_decode($data);
        $this->userId = $json->user_id;
        $this->currencies = $json->currencies;
        $this->store = $json->store;

        $this->userService = new UserService();
    }

    public static function getUniqueKey()
    {
        $params = func_get_args();
        if (count($params) === 2) {
            $params[] = null;
        }

        $userId = $params[0];
        $currencies = $params[1];
        $store = $params[2];
        return $userId . '_' . $store . '_' . implode('_', $currencies);
    }

    public static function serializeData()
    {
        $params = func_get_args();
        if (count($params) === 2) {
            $params[] = null;
        }

        $userId = $params[0];
        $currencies = $params[1];
        $store = $params[2];

        return json_encode([
            'user_id' => $userId,
            'currencies' => $currencies,
            'store' => $store,
        ]);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        parent::handle();
        DB::connection('master')->beginTransaction();
        DB::connection('master')->getPdo()->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
        try {
            $balances = [
                Consts::TYPE_MAIN_BALANCE => $this->userService->getUserBalances($this->userId, $this->currencies, true, Consts::TYPE_MAIN_BALANCE),
                Consts::TYPE_MARGIN_BALANCE => $this->userService->getUserBalances($this->userId, $this->currencies, true, Consts::TYPE_MARGIN_BALANCE),
                Consts::TYPE_MAM_BALANCE => $this->userService->getUserBalances($this->userId, $this->currencies, true, Consts::TYPE_MAM_BALANCE),
                Consts::TYPE_EXCHANGE_BALANCE => $this->userService->getUserBalances($this->userId, $this->currencies, true, Consts::TYPE_EXCHANGE_BALANCE),
            ];

            $balances[Consts::TYPE_AIRDROP_BALANCE] = @$this->userService->getUserAccounts($this->userId, Consts::TYPE_AIRDROP_BALANCE)[Consts::TYPE_AIRDROP_BALANCE] ?? [];

            if ($this->store) {
                static::fireEvent($this->userId, $this->store, $balances);
            } else {
                static::fireEvent($this->userId, Consts::TYPE_MAIN_BALANCE, $balances);
                static::fireEvent($this->userId, Consts::TYPE_MARGIN_BALANCE, $balances);
                static::fireEvent($this->userId, Consts::TYPE_MAM_BALANCE, $balances);
                static::fireEvent($this->userId, Consts::TYPE_EXCHANGE_BALANCE, $balances);
                static::fireEvent($this->userId, Consts::TYPE_AIRDROP_BALANCE, $balances);
            }

//            event(new BalanceUpdated($this->userId, $balances));
            DB::connection('master')->commit();
        } catch (Exception $e) {
            DB::connection('master')->rollBack();
            Log::error($e);
            Log::error("--- SendBalance --- {$this->userId} --- {$this->currencies} --- {$this->store}");
            throw $e;
        }
    }

    public static function fireEvent($userId, $store, $balances)
    {
        $balance = $balances[$store];
        $eventClass = static::getEventClass($store);
        event(new $eventClass($userId, $balance));
    }

    public static function getEventClass($store)
    {
        switch ($store) {
            case Consts::TYPE_MAIN_BALANCE:
                return MainBalanceUpdated::class;
            case Consts::TYPE_MARGIN_BALANCE:
                return MamBalanceUpdated::class;
            case Consts::TYPE_MAM_BALANCE:
                return MamBalanceUpdated::class;
            case Consts::TYPE_EXCHANGE_BALANCE:
                return SpotBalanceUpdated::class;
            case Consts::TYPE_AIRDROP_BALANCE:
                return AirdropBalanceUpdated::class;
            default:
                return MainBalanceUpdated::class;
        }
    }
}
