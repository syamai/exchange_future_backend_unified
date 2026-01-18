<?php

namespace Transaction\Models;

use App\Consts;
use App\Models\BlockchainAddress;
use App\Models\User;
use App\Models\UserInformation;
use App\Utils;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    public $table = 'transactions';

    const PENDING_STATUS = 'pending';
    const PROCESSING_STATUS = 'processing';
    const APPROVED_STATUS = 'approved';
    const REJECTED_STATUS = 'rejected';
    const SUCCESS_STATUS = 'success';
    const CANCELED_STATUS = 'canceled';

    public $fillable = [
        'approve_at',
        'approved_by',
        'amount',

        'bank_name',
        'blockchain_address',

        'cancel_at',
        'created_at',

        'currency',
        'network_id',
        'transaction_id',

        'deposit_code',
        'deny_at',
        'deny_by',
        'blockchain_sub_address',

        'error_detail',

        'from_address',
        'fee',
        'is_external',

        'remarks',
        'reject_by',
        'reject_at',

        'send_confirmer1',
        'send_confirmer2',
        'sent_by',
        'sent_at',
        'status',
        'collect',

        'to_address',
        'tx_hash',
        'transaction_date',

        'updated_at',
        'user_id',

        'verify_code'
    ];

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function userInformation()
    {
        return $this->belongsTo(UserInformation::class, 'user_id', 'user_id');
    }

    public function trezor()
    {
        return $this->belongsTo(BlockchainAddress::class, 'blockchain_address', 'from_address');
    }

    public function scopeFilterExternalWithdraw($query, $params = [])
    {
        $sort = \Arr::get($params, 'sort');
        $sortType = \Arr::get($params, 'sort_type');
        $type = \Arr::get($params, 'type');
        $currency = \Arr::get($params, 'currency');
        $keySearch = \Arr::get($params, 'key_search');
        $start_date = \Arr::get($params, 'start_date');
        $end_date = \Arr::get($params, 'end_date');

        $query->filterWithdraw()
        ->where('is_external', 1)

        ->when($start_date&&$end_date, function ($query) use ($start_date, $end_date) {
            $query->where('transactions.created_at', '>', $start_date);
            $query->where('transactions.created_at', '<', $end_date);
        })
        ->when($type, function ($query) use ($type) {
            $query->where('transactions.status', $type);
        })
        ->when($currency, function ($query) use ($currency) {
            $query->where('transactions.currency', $currency);
        })
        ->when($type, function ($query) use ($type) {
            $query->where('transactions.status', $type);
        })
        ->when($sort, function ($query) use ($sortType, $sort) {
            $query->orderBy($sort, $sortType ?? 'desc')
                ->orderBy('transactions.created_at', $sortType ?? 'desc');
        }, function ($query) {
            $query->orderBy('transactions.created_at', 'desc');
        })
        ->when(!is_null($keySearch), function ($query) use ($keySearch) {
            $query->where(function ($builderQuery) use ($keySearch) {
                // $keySearch = Utils::escapeLike($keySearch);
                $builderQuery->orWhere('users.email', 'like', "%$keySearch%")
                    ->orWhere('transactions.to_address', 'like', "%$keySearch%")
                    ->orWhere('transactions.tx_hash', 'like', "%$keySearch%")
                    ->orWhere('transactions.transaction_id', 'like', "%$keySearch%");
            });
        });

        return $query;
    }

    public function scopeFilterWithdraw($query)
    {
        return $query->where('amount', '<', 0);
    }

    public function scopeFilterDeposit($query)
    {
        return $query->where('amount', '>', 0);
    }
    public function scopeTotalDepositWithdrawNotBonus($query, $deposit_amount)
    {
        return $query
        ->select('user_id', \DB::raw("SUM(amount) as total_amount"))
        ->where('collect', Consts::DEPOSIT_TRANSACTION_COLLECTED_STATUS)
        ->where(function ($q) {
            $q->whereNotIn('from_address', Consts::DEPOSIT_BONUS);
            $q->orWhereNull('from_address');
        })
        ->groupBy('user_id')
        ->havingRaw("SUM(amount)  > ?", [$deposit_amount]);
    }
}
