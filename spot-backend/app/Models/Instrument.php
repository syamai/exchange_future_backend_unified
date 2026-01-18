<?php

namespace App\Models;

use App\Consts;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Instrument extends Model
{
    public $table = 'instruments';

    public $fillable = [
        'symbol',
        'root_symbol',
        'state',
        'type',
        'expiry',
        'base_underlying',
        'quote_currency',
        'underlying_symbol',
        'settle_currency',
        'init_margin',
        'maint_margin',
        'deleverageable',
        'maker_fee',
        'taker_fee',
        'settlement_fee',
        'has_liquidity',
        'reference_index',
        'funding_base_index',
        'funding_quote_index',
        'funding_premium_index',
        'funding_interval',
        'tick_size',
        'max_price',
        'max_order_qty',
        'multiplier',
        'option_strike_price',
        'option_ko_price',
        'risk_limit',
        'risk_step',
        'settlement_index',
        'timestamps',

    ];


    public function extra()
    {
        return $this->hasOne('App\Models\InstrumentExtraInformations', 'id');
    }

    public function close()
    {
        $this->state = Consts::INSTRUMENT_STATE_CLOSE;
        $this->save();
    }

    public function isExpired()
    {
        return $this->expiry && Carbon::now()->gt($this->expiry);
    }

    public function isClosed()
    {
        return $this->state === Consts::INSTRUMENT_STATE_CLOSE;
    }
}
