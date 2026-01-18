<?php
namespace App\Http\Services;

use App\Consts;
use App\Models\Instrument;
use App\Models\InstrumentExtraInformations;
use App\Models\IndexSetting;
use App\Models\MarginContractSetting;
use Illuminate\Support\Arr;

class InstrumentService
{
    public function getInstruments($params)
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $getInstruments = Instrument::when(array_key_exists('search', $params), function ($query) use ($params) {
            $searchKey = $params['search'];
            logger($searchKey);
            $model = new Instrument;
            $fields = $model->fillable;
            foreach ($fields as $field) {
                if (!in_array($field, Consts::FIELD_NOT_SEARCH_INSTRUSMENT)) {
                    logger($field);
                    $query->orWhere($field, 'like', '%' . $searchKey . '%');
                }
            }
            return $query;
        })
        ->when(
            !empty($params['sort']) && !empty($params['sort_type']),
            function ($query) use ($params) {
                return $query->orderBy($params['sort'], $params['sort_type']);
            },
            function ($query) use ($params) {
                return $query->orderBy('updated_at', 'desc');
            }
        )->paginate($limit);
        return $getInstruments;
    }

    public function getInstrumentsSettings()
    {
        return IndexSetting::where('status', 'active')->get();
    }

    public function updateInstruments($params): bool
    {
        $id = $params["id"];
        $instrument = Instrument::where('id', $id)->first();
        if (!$instrument) {
            return false;
        }
        $res = $instrument->fill($params)->save();

        $instrument_extra = InstrumentExtraInformations::where('id', $id)->first();
        if (!$instrument_extra) {
            return false;
        }
        $res = $instrument_extra->fill($params)->save();
        return $instrument;
    }

    public function createInstruments($params)
    {
        $res_instrument = Instrument::create($params);
        InstrumentExtraInformations::create($params);
        MarginContractSetting::insert(['symbol' => $params['symbol']]);
        return $res_instrument;
    }

    public function deleteInstruments($id): bool
    {
        $instrument = Instrument::where('id', $id)->first();
        if (!$instrument) {
            return true;
        }
        $res = $instrument->delete();

        $instrument_extra = InstrumentExtraInformations::where('id', $id)->first();
        if (!$instrument_extra) {
            return true;
        }
        $res = $instrument_extra->delete();
        return $instrument;
    }

    public function getCoinActive()
    {
        $coins = IndexSetting::where('status', Consts::USER_ACTIVE)->distinct('root_symbol')->pluck('root_symbol');
        return $coins;
    }

    public function getIndexCoinActive($params)
    {
        $field = $params["type"];
        if ($field == "ALL") {
            $coinIndexs = IndexSetting::where('status', Consts::USER_ACTIVE)->pluck('symbol');
        } else {
            $coinIndexs = IndexSetting::where('status', Consts::USER_ACTIVE)->where('root_symbol', $field)->pluck('symbol');
        }
        return $coinIndexs;
    }
}
