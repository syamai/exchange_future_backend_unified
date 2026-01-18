<?php
/**
 * Created by PhpStorm.

 * Date: 5/27/19
 * Time: 7:10 PM
 */

namespace Transaction\Http\Services;

use App\Consts;
use App\Utils;
use Transaction\Models\Transaction;

class DepositService
{

    /**
     * @param array $params
     * @return mixed
     */
    public function buildGetDepositHistory($params = [])
    {
        $sort = \Arr::get($params, 'sort');
        $sortType = \Arr::get($params, 'sort_type');
        $keySearch = \Arr::get($params, 'key_search');
        $startDate = \Arr::get($params, 'startDate');
        $endDate = \Arr::get($params, 'endDate');

        return Transaction::select(
            'users.email',
            'transactions.created_at',
            'transactions.currency',
            'transactions.fee',
            'transactions.status',
            'transactions.amount',
            'transactions.transaction_id',
            'transactions.id',
            'transactions.tx_hash',
            'transactions.to_address'
        )
            ->join('users', 'users.id', 'transactions.user_id')
            ->where('transactions.amount', '>', 0)
            ->when($sort, function ($query) use ($sortType, $sort) {
                $query->orderBy($sort, $sortType ?? 'desc')
                    ->orderBy('transactions.created_at', $sortType ?? 'desc');
            }, function ($query) {
                $query->orderBy('transactions.created_at', 'desc')
                    ->orderBy('users.email', 'desc');
            })
            ->when(!is_null($keySearch), function ($query) use ($keySearch) {
                $query->where(function ($builderQuery) use ($keySearch) {
                    // $keySearch = Utils::escapeLike($keySearch);
                    $builderQuery->orWhere('users.email', 'like', "%$keySearch%")
                        ->orWhere('transactions.tx_hash', 'like', "%$keySearch%")
                        ->orWhere('transactions.transaction_id', 'like', "%$keySearch%");
                });
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query
                    ->where('transactions.created_at', '>=', $startDate)
                    ->where('transactions.created_at', '<=', $endDate);
            });
    }


    /**
     * @param $params
     * @return mixed
     */
    public function getDepositHistory($params)
    {
        $limit  = \Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $query = $this->buildGetDepositHistory($params);
        return $query->paginate($limit);
    }
}
