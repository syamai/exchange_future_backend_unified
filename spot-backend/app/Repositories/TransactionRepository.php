<?php

namespace App\Repositories;

use App\Consts;
use Transaction\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionRepository extends BaseRepository
{
    /**
     * Configure the Model
     **/
    public function model()
    {
        return Transaction::class;
    }

    public function create($input)
    {
        return Transaction::create($input); // <== TODO user super method
    }

    public function getHistory($params, $limit = Consts::DEFAULT_PER_PAGE)
    {
        return $this->buildHistoryQuery($params)->paginate($limit);
    }

    public function getUserTransactions($params)
    {
        $userId = Auth::id();
        $startDate = $params['start'];
        $endDate = $params['end'];
        return DB::select(
            '
            SELECT a.*, ending_balance
            FROM (SELECT currency, SUM(debit) AS debit, SUM(credit) AS credit, MAX(id) AS max_id,
                        SUM(CASE WHEN type="transfer" THEN debit ELSE 0 END) AS deposit,
                        SUM(CASE WHEN type="transfer" THEN credit ELSE 0 END) AS withdraw
                    FROM user_transactions
                    WHERE user_id = ? AND created_at >= ? AND created_at <= ? GROUP BY currency) AS a
                  JOIN user_transactions ON a.max_id = user_transactions.id',
            [$userId, $startDate, $endDate]
        );
    }

    public function exportHistory($params)
    {
        return $this->buildHistoryQuery($params)
            ->whereIn('status', [
                Consts::TRANSACTION_STATUS_SUCCESS,
                Consts::TRANSACTION_STATUS_PENDING,
                Consts::TRANSACTION_STATUS_CANCEL,
                Consts::TRANSACTION_STATUS_ERROR,
            ])
            ->get();
    }

    private function buildHistoryQuery($params)
    {
        $userId = Auth::id();

        $query = Transaction::when($params['currency'], function ($query) use ($params) {
            $query->where('transactions.currency', $params['currency']);
        })->where('user_id', $userId);
        $query = $query->leftJoin('networks', function ($join) {
            $join->on('transactions.network_id', '=', 'networks.id');
        });

        if (array_key_exists('start', $params) && (!empty($params['start']))) {
            $query = $query->where('transactions.created_at', '>=', $params['start']);
        }

        if (array_key_exists('end', $params) && (!empty($params['end']))) {
            $query = $query->where('transactions.created_at', '<', $params['end']);
        }

        if (array_key_exists('type', $params) && (!empty($params['type']))) {
            if ($params['type'] == Consts::TRANSACTION_TYPE_DEPOSIT) {
                $query = $query->where('amount', '>=', 0);
            } else {
                $query = $query->filterWithdraw();
            }
        }

        $query->when(array_key_exists('sort', $params) && (!empty($params['sort'])), function ($query) use ($params) {
            $query->orderBy($params['sort'], $params['sort_type']);
        }, function ($query) {
            $query->orderBy('created_at', 'desc');
        });

        $query->select(
            'transactions.id',
            'transactions.blockchain_address',
            'transactions.created_at',
            'transactions.currency',
            'transactions.network_id',
            'networks.name as network_name',
            'transactions.transaction_id',
            'transactions.blockchain_sub_address',
            'transactions.from_address',
            'transactions.fee',
            'transactions.status',
            'transactions.tx_hash',
            'transactions.transaction_date',
            'transactions.updated_at',
            'transactions.to_address',
            'transactions.user_id',
            'transactions.is_external',
            DB::raw('ABS(amount) as amount')
        );

        return $query;
    }
}
