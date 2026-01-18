<?php
/**
 * Created by PhpStorm.
 * Date: 7/29/19
 * Time: 10:21 AM
 */

namespace Snapshot\Http\Services;

use Snapshot\Models\TakeProfit;

class TakeProfitService
{
    protected $model;

    public function __construct(TakeProfit $model)
    {
        $this->model = $model;
    }

    public function myPaginate($input)
    {
        return $this->model->filter($input)->paginate();
    }

    public function statistic()
    {
        return $this->model->groupBy('currency')
            ->selectRaw('sum(amount) as total, currency ')
            ->pluck('total', 'currency');
    }

    public function store($input)
    {
        return $this->model->create($input);
    }

    public function getListProfitByHours(array $input)
    {
        $param = $input;
        $dataRaw = $this->model->select('currency')->distinct();
        return $dataRaw;
    }
}
