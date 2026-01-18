<?php

namespace App\Http\Services;

use App\Consts;
use App\Enums\StatusVoucher;
use App\Mail\SendVoucherForUser;
use App\Models\Leaderboard;
use App\Models\User;
use App\Models\UserTradeVolumePerDay;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VoucherService
{
    public function getListVoucher($request)
    {
        $limit = Arr::get($request, 'limit', Consts::DEFAULT_PER_PAGE) ?? Consts::DEFAULT_PER_PAGE;
        $userId = request()->user()->id;
        $query = "SELECT vouchers.*, temp_table.conditions_use_old AS conditions_use_old,temp_table.amount_old AS amount_old,temp_table.expires_date AS expires_date, temp_table.user_id AS user_id, CASE ".
            "WHEN `temp_table`.`user_id` IS NULL THEN '". StatusVoucher::AVAILABLE->value."'".
            "WHEN `temp_table`.`expires_date` < now() THEN '".StatusVoucher::EXPIRED->value."'".
            "ELSE `temp_table`.`status`".
            "END AS status_use, COALESCE(trading_table.volume, 0) AS total_trading FROM vouchers ".
            "LEFT JOIN (SELECT * FROM user_vouchers where user_vouchers.user_id = ". $userId .") AS temp_table ".
            "ON vouchers.id = temp_table.voucher_id LEFT JOIN (SELECT * FROM user_trade_volume_per_days WHERE".
            " user_trade_volume_per_days.user_id = ".$userId.") AS trading_table ON (vouchers.id = trading_table.voucher_id AND trading_table.type = vouchers.type) ".
            "where ((temp_table.user_id IS NULL and deleted_at IS NULL) OR temp_table.user_id = ". $userId .")";
        if ($request->type) {
            $query .= ' AND vouchers.type = "'. $request->type .'"';
        }
        if ($request->status && strtolower($request->status) == StatusVoucher::AVAILABLE->value) {
            $query .= ' AND (`temp_table`.`user_id` IS NULL AND IF(`temp_table`.`user_id` IS NULL,"'. StatusVoucher::AVAILABLE->value .'",`temp_table`.`status`) = "'. strtolower($request->status) .'")';
        } elseif ($request->status && strtolower($request->status) == 'claim') {
            $query .= ' AND `temp_table`.`expires_date` >= now()  AND IF(`temp_table`.`user_id` IS NULL,"'. StatusVoucher::AVAILABLE->value .'",`temp_table`.`status`) = "'. StatusVoucher::AVAILABLE->value .'"';
        } elseif ($request->status == StatusVoucher::EXPIRED->value) {
            $query .= ' AND `temp_table`.`expires_date` < now() ';
        } elseif ($request->status) {
            $query .= ' AND `temp_table`.`expires_date` >= now()  AND IF(`temp_table`.`user_id` IS NULL,"'. StatusVoucher::AVAILABLE->value .'",`temp_table`.`status`) = "'. StatusVoucher::REDEEMED->value .'"';
        }
        $query .= ' ORDER BY vouchers.id DESC';

        return paginate(DB::select($query), $limit);
    }


    public function getList($req)
    {
        $query = Voucher::query();
        if ($req->type) {
            $query->where('type', $req->type);
        }
        if ($req->status) {
            $query->where('status', $req->status);
        }

        if ($req->keyword) {
            $query->where('name', 'like', "%". $req->keyword."%")
                ->orWhere('currency', 'like', "%". $req->keyword."%");
        }
        $query->orderBy('id', 'DESC');

        return $query->paginate(Arr::get(request(), 'limit', Consts::DEFAULT_PER_PAGE));
    }

    public function update($id, $request)
    {
        $voucher = Voucher::findOrFail($id);
        $voucher->name = $request->name;
        $voucher->type = $request->type;
        $voucher->currency = $request->currency;
        $voucher->amount = $request->amount;
        $voucher->number = $request->number;
        $voucher->conditions_use = $request->conditions_use;
        $voucher->expires_date_number = $request->expires_date_number;
        $voucher->save();

        return $voucher;
    }

    public function destroy($id)
    {
        $voucher = Voucher::findOrFail($id);

        return $voucher->delete();
    }

    public function getUserVoucher($inputs, $status, $isExpires = false)
    {
        $userVoucher = UserVoucher::query()->where([
            'voucher_id' => $inputs->voucher_id,
            'status' => $status,
            'user_id' => Auth::id()
        ]);

        if ($isExpires) {
            $userVoucher->where('expires_date', '>=', Carbon::now()->format('Y-m-d H:i:s'));
        }
        return $userVoucher->first();
    }
    public function sendVoucherForUser()
    {
        $vouchers = Voucher::all();
        if (!$vouchers->count()) {
            return;
        }
        $users = Leaderboard::query()
            ->selectRaw('COALESCE(SUM(trading_volume), 0) as total, user_id, type')
            ->groupBy('user_id', 'type')
            ->get();
        $voucherUses = UserVoucher::query()->select('user_id', 'voucher_id')->get()->toArray();
        $voucherForUsers = [];
        foreach ($vouchers->toArray() as $voucher) {
            foreach ($users->toArray() as $user) {
                $object = [
                    'user_id' => $user['user_id'],
                    'voucher_id' => $voucher['id'],
                ];
                $usedVoucher = collect($voucherUses)->filter(function ($item) use ($object) {
                    return json_encode($item) == json_encode($object);
                })->first();

                if (!$usedVoucher && $user['type'] == $voucher['type'] && BigNumber::new($user['total'])->mul(-1)->add($voucher['conditions_use'])->toString() <= 0) {
                    $temp = $voucher;
                    $temp['user_id'] = $user['user_id'];
                    $voucherForUsers [] = $temp;
                }
            }
        }

        DB::beginTransaction();
        try {
            if (count($voucherForUsers)) {
                collect($voucherForUsers)->map(function ($user) {
                    $data = [
                        'voucher_id' => $user['id'],
                        'expires_date' => $user['expires_date_number'] ? Carbon::now()->addDays($user['expires_date_number'])->format('Y-m-d H:i:s') : null,
                        'status' => StatusVoucher::AVAILABLE->value,
                        'user_id' => $user['user_id'],
                    ];
                    UserVoucher::create($data);
                    Mail::queue(new SendVoucherForUser($user));
                });
                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            throw $e;
        }
    }

    public function updateVouchersExpired($model)
    {
        return $model::whereNotNull('expires_date')
            ->where('status', '!=', StatusVoucher::EXPIRED->value)
            ->whereDate('expires_date', '<', Carbon::now()->format('Y-m-d H:i:s'))
            ->update(['status' => StatusVoucher::EXPIRED->value]);
    }

    /*
     * Params ["user_id","balance"]
     */
    public function addBalanceVoucherFuture(array $params)
    {
        if (!isset($params['user_id']) || !$params['balance']) {
            throw new \HttpException(422, __('exception.request.error'));
        }
        $user = User::findOrFail($params['user_id']);
        $vouchers = Voucher::whereNull('deleted_at')
            ->where('type', Consts::TYPE_FUTURE_BALANCE)
            ->get();
        $volume = $params['balance'];
        $symbol = $params['symbol'];
        $pricePairUSDUSDT = app(PriceService::class)->getPrice('usd', 'usdt')->price;

        if (str_contains($symbol, 'USDT')) {
            $isUSD = false;
        } else {
            $isUSD = true;
        }

        foreach ($vouchers as $voucher) {
            $voucherUser = UserTradeVolumePerDay::where('voucher_id', $voucher->id)
                ->where('user_id', $user->id)
                ->first();

            if ($voucherUser) {
                if ($isUSD) {
                    $priceUsdtWithCoin = BigNumber::new(1)->div($pricePairUSDUSDT);
                    $priceVolume = BigNumber::new($volume)->mul($priceUsdtWithCoin)->toString();
                    $updateVolume = BigNumber::new($priceVolume)->add($voucherUser->volume)->toString();
                } else {
                    $updateVolume = BigNumber::new($volume)->add($voucherUser->volume)->toString();
                }
                $voucherUser->volume = $updateVolume;
                $voucherUser->save();
                $orderService = new OrderService();
                $orderService->availableVoucher($voucherUser->volume, $voucher, $user->id, Consts::TYPE_FUTURE_BALANCE);
            } else {
                $newVoucherUser = new UserTradeVolumePerDay();
                $newVoucherUser->user_id = $user->id;
                $newVoucherUser->voucher_id = $voucher->id;
                $newVoucherUser->type = Consts::TYPE_FUTURE_BALANCE;
                if ($isUSD) {
                    $priceUsdtWithCoin = BigNumber::new(1)->div($pricePairUSDUSDT);
                    $priceVolume = BigNumber::new($volume)->mul($priceUsdtWithCoin)->toString();
                    $newVoucherUser->volume = $priceVolume;
                } else {
                    $newVoucherUser->volume = $volume;
                }
                $newVoucherUser->save();
                $orderService = new OrderService();
                $orderService->availableVoucher($newVoucherUser->volume, $voucher, $user->id, Consts::TYPE_FUTURE_BALANCE);
            }
        }

        return true;
    }
}
