<?php

namespace App\Http\Services;

use App\Consts;
use App\Models\GainsStatisticsOverview;
use App\Models\LosersStatisticsOverview;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Transaction\Models\Transaction;

class UsersStatisticsOverviewService
{
    private $priceService;

    public function __construct(PriceService $priceService)
    {
        $this->priceService = $priceService;
    }


    public function usersNoDeposit($request)
    {
        $limit = Arr::get($request, 'limit', Consts::DEFAULT_PER_PAGE);
        return User::NoDeposit()
        ->where('type', '<>', 'bot')
            ->selectRaw('id, uid, name, email, created_at as registerDate')
            ->orderByDesc('created_at')
            ->paginate($limit)
            ->withQueryString();
    }

    public function topDeposit($request, $currency)
    {
        return match ($currency) {
            'all' => $this->topDepositAll($request),
            default => $this->topDepositCurrency($request, $currency)
        };
    }

    public function topWithdraw($request, $currency)
    {
        return match ($currency) {
            'all' => $this->topWithdrawAll($request),
            default => $this->topWithdrawCurrency($request, $currency)
        };
    }

    public function topDepositAll($request)
    {
        $limit = Arr::get($request, 'limit', Consts::DEFAULT_PER_PAGE);

        return DB::table('users as u')
        ->where('u.type', '<>', 'bot')
            ->join('transactions as t', 'u.id', '=', 't.user_id')
            ->where('t.amount', '>', 0)
            ->where('t.status', Consts::TRANSACTION_STATUS_SUCCESS)
            ->selectRaw('u.id, u.uid, u.name, u.email, u.created_at as registerDate, t.currency, SUM(t.amount) as totalAmount')
            ->groupBy('u.id', 'u.created_at', 't.currency')
            ->orderByDesc('totalAmount')
            ->paginate($limit)
            ->withQueryString();
    }


    public function topDepositCurrency($request, $currency)
    {
        $limit = Arr::get($request, 'limit', Consts::DEFAULT_PER_PAGE);

        return User::select(DB::raw('id, uid, name, email, created_at as registerDate')) // only real fields
            ->whereHas('transactions', function ($q) use ($currency) {
                $q->where('currency', $currency);
                $q->where('status', Consts::TRANSACTION_STATUS_SUCCESS);
                $q->FilterDeposit();
            })
            ->withSum(['transactions as totalAmount' => function ($q) use ($currency) {
                $q->where('currency', $currency);
                $q->where("status", Consts::TRANSACTION_STATUS_SUCCESS);
                $q->FilterDeposit();
            }], 'amount')
            ->orderByDesc('totalAmount')
            ->paginate($limit)
            ->withQueryString();
    }

    public function topWithdrawAll($request)
    {
        $limit = Arr::get($request, 'limit', Consts::DEFAULT_PER_PAGE);

        return DB::table('users as u')
        ->where('u.type', '<>', 'bot')
            ->join('transactions as t', 'u.id', '=', 't.user_id')
            ->where('t.amount', '<', 0)
            ->where('t.status', Consts::TRANSACTION_STATUS_SUCCESS)
            ->selectRaw('u.id, u.uid, u.name, u.email, u.created_at as registerDate, t.currency, SUM(abs(t.amount)) as totalAmount')
            ->groupBy('u.id', 'u.created_at', 't.currency')
            ->orderByDesc('totalAmount')
            ->paginate($limit)
            ->withQueryString();
    }


    public function topWithdrawCurrency($request, $currency)
    {
        $limit = Arr::get($request, 'limit', Consts::DEFAULT_PER_PAGE);

        return User::select(DB::raw('id, uid, name, email, created_at as registerDate')) // only real fields
        ->where('type', '<>', 'bot')
            ->whereHas('transactions', function ($q) use ($currency) {
                $q->where('currency', $currency);
                $q->where('status', Consts::TRANSACTION_STATUS_SUCCESS);
                $q->FilterWithdraw();
            })
            ->withSum(['transactions as totalAmount' => function ($q) use ($currency) {
                $q->where('currency', $currency);
                $q->where("status", Consts::TRANSACTION_STATUS_SUCCESS);
                $q->FilterWithdraw();
                $q->select(DB::raw('SUM(ABS(amount))'));
            }], 'amount')
            ->orderByDesc('totalAmount')
            ->paginate($limit)
            ->withQueryString();
    }


    public function pendingWithdrawAll($request)
    {
        $limit = Arr::get($request, 'limit', Consts::DEFAULT_PER_PAGE);

        return DB::table('users as u')
        ->where('u.type', '<>', 'bot')
            ->join('transactions as t', 'u.id', '=', 't.user_id')
            ->where('t.amount', '<', 0)
            ->where('t.status', Consts::TRANSACTION_STATUS_PENDING)
            ->selectRaw('u.id, u.uid, u.name, u.email, t.status as status, t.currency, abs(t.amount) as amount, t.created_at as timestamp')
            ->orderByDesc('amount')
            ->paginate($limit)
            ->withQueryString();
    }

    public function topGains($request)
    {
        $limit = Arr::get($request, 'limit', Consts::DEFAULT_PER_PAGE);

        return GainsStatisticsOverview::query()
            ->orderByDesc('total_asset_value')
            ->paginate($limit);
    }

    public function topLosers($request)
    {
        $limit = Arr::get($request, 'limit', Consts::DEFAULT_PER_PAGE);

        return LosersStatisticsOverview::query()
            ->orderByDesc('asset_reduction_percent')
            ->paginate($limit);
    }
}
