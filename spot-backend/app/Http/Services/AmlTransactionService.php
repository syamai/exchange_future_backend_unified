<?php
/**
 * Created by PhpStorm.
 * Date: 4/15/19
 * Time: 1:14 PM
 */

namespace App\Http\Services;

use App\Consts;
use App\Models\AmalTransaction;
use App\Models\AmalCashBack;
use App\Models\User;
use DateTime;

class AmlTransactionService
{
    private $model;

    public function __construct(AmalTransaction $model)
    {
        $this->model = $model;
    }

    public function index($input)
    {
        $input['user_id'] = auth()->id();
        $per_page = request('per_page', Consts::DEFAULT_PER_PAGE);
        return $this->model
            ->filter($input)
            ->orderBy(request('sort', 'id'), request('sort_type', 'desc'))
            ->paginate($per_page);
    }

    public function getCashBack($input)
    {
        $input['user_id'] = auth()->id();
        $per_page = request('per_page', Consts::DEFAULT_PER_PAGE);

        $data = AmalCashBack::filter($input)
            ->orderBy(request('sort', 'id'), request('sort_type', 'desc'))
            ->paginate($per_page);
        foreach ($data->items() as $row) {
            $splited_str = explode('.', $row->referred_email);
            $row->referred_email = substr($row->referred_email, 0, 2) . '***@***.' . end($splited_str);
        }

        return $data;
    }

    public function store($input)
    {
        $data = app(BuyAmlService::class)->buy($input);
        return $this->model->create($data);
    }

    public function show($id)
    {
        return $this->model->find($id);
    }

    public function edit($id)
    {
        return $this->model->find($id);
    }

    public function update($input, $id)
    {
        $amlTransaction = $this->model->find($id);
        if (empty($amlTransaction)) {
            return $amlTransaction;
        }
        return $this->model->where('id', $id)->update($input);
    }

    public function destroy($id)
    {
        $amlTransaction = $this->model->find($id);
        if (empty($amlTransaction)) {
            return $amlTransaction;
        }
        return $this->model->where('id', $id)->delete();
    }

    public function getData($input, $select = '*')
    {
        $input = escapse_string_params($input);
        $input['user_id'] = auth()->id();
        return $this->model
            ->filter(['user_id' => $input['user_id']])
            ->orderBy(request('sort', 'id'), request('sort_type', 'desc'))
            ->selectRaw($select)
            ->get();
    }

    public function getDataby6Month($input, $select = '*')
    {
        $input = escapse_string_params($input);
        $input['user_id'] = auth()->id();
        return $this->model
            ->where('created_at', '>', now()->subMonth(6))
            ->filter(['user_id' => $input['user_id']])
            ->orderBy(request('sort', 'id'), request('sort_type', 'desc'))
            ->selectRaw($select)
            ->get();
    }

    public function getCashBackDataby6Month($input, $select = '*')
    {
        $input = escapse_string_params($input);
        $input['user_id'] = auth()->id();
        return AmalCashBack::where('created_at', '>', now()->subMonth(6))
            ->filter(['user_id' => $input['user_id']])
            ->orderBy(request('sort', 'id'), request('sort_type', 'desc'))
            ->selectRaw($select)
            ->get();
    }

    public function getBuyHistory($params, $userId = null)
    {
        return $this->buildGetBuyHistoryQuery($params, $userId)->paginate($params['limit']);
    }

    public function getCashBackHistory($params, $userId = null)
    {
        return $this->buildGetCashBackHistoryQuery($params, $userId)->paginate($params['limit']);
    }

    private function buildGetBuyHistoryQuery($params, $userId)
    {
        $query = AmalTransaction::join('users', 'amal_transactions.user_id', '=', 'users.id')
        ->when(array_key_exists('start_date', $params), function ($query) use ($params) {
            $now = new DateTime();
            $startDate = $params['start_date'];
            $endDate = isset($params['end_date']) ? $params['end_date'] : $now->format('Y-m-d H:i:s');
            return $query->whereBetween('amal_transactions.created_at', array($startDate, $endDate));
        })
        ->when(array_key_exists('currency', $params), function ($query) use ($params) {
            return $query->where('amal_transactions.currency', $params['currency']);
        })
        ->when(array_key_exists('search_key', $params), function ($query) use ($params) {
            return $query->where('users.email', 'like', '%' . $params['search_key'] . '%');
        })
        ->select(
            'amal_transactions.id',
            'amal_transactions.user_id',
            'amal_transactions.amount',
            'amal_transactions.currency',
            'amal_transactions.bonus',
            'amal_transactions.total',
            'amal_transactions.price',
            'amal_transactions.price_bonus',
            'amal_transactions.payment',
            'amal_transactions.created_at',
            'amal_transactions.updated_at',
            'users.email'
        )
        ->when(!empty($params['sort']), function ($query) use ($params) {
            $query->orderBy($params['sort'], isset($params['sort_type']) ? $params['sort_type'] : 'desc');
        }, function ($query) {
            $query->orderBy('amal_transactions.created_at', 'desc');
        });

        return $query;
    }

    private function buildGetCashBackHistoryQuery($params, $userId)
    {
        $query = AmalCashBack::when(array_key_exists('start_date', $params), function ($query) use ($params) {
            $now = new DateTime();
            $startDate = $params['start_date'];
            $endDate = isset($params['end_date']) ? $params['end_date'] : $now->format('Y-m-d H:i:s');
            return $query->whereBetween('created_at', array($startDate, $endDate));
        })
        ->when(array_key_exists('currency', $params), function ($query) use ($params) {
            return $query->where('currency', $params['currency']);
        })
        ->when(array_key_exists('search_key', $params), function ($query) use ($params) {
            return $query->where('referrer_email', 'like', '%' . $params['search_key'] . '%')
                         ->orWhere('referred_email', 'like', '%' . $params['search_key'] . '%');
        })
        ->select(
            'id',
            'user_id',
            'referrer_email',
            'referred_email',
            'currency',
            'rate',
            'bonus',
            'created_at'
        )
        ->when(!empty($params['sort']), function ($query) use ($params) {
            $query->orderBy($params['sort'], isset($params['sort_type']) ? $params['sort_type'] : 'desc');
        }, function ($query) {
            $query->orderBy('created_at', 'desc');
        });

        return $query;
    }
}
