<?php

namespace App\Http\Services;

use App\Consts;
use App\Models\PriceGroup;
use App\Utils;
use Illuminate\Support\Facades\DB;

class PriceGroupService
{
    private $model;

    public function __construct(PriceGroup $model)
    {
        $this->model = $model;
    }

    public function getListCurrency()
    {
        $query = DB::table('price_groups')->select('currency')->distinct()->get();
        return $query;
    }
}
