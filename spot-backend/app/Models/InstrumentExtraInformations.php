<?php

namespace App\Models;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;

class InstrumentExtraInformations extends Model
{

    public $table = 'instrument_extra_informations';
    public $fillable = [
        'symbol',
        'impact_bid_price',
        'impact_mid_price',
        'impact_ask_price',
        'fair_basis_rate',
        'fair_basis',
        'fair_price',
        'mark_price',
        'funding_timestamp',
        'funding_rate',
        'indicativefunding_rate',
        'last_price',
        'last_price_24h',
        'ask_price',
        'bid_price',
        'mid_price',
        'trade_reported_at',
        'max_value_24h',
        'min_value_24h',
        'total_turnover_24h',
        'total_volume_24h',
        'total_volume',
    ];
}
