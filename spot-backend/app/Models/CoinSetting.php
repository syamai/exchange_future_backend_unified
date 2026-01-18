<?php

namespace App\Models;

use App\Utils;
use Illuminate\Database\Eloquent\Model;

class CoinSetting extends Model
{
    protected $table = 'coin_settings';

    protected $fillable = [
        'coin',
        'currency',
        'quantity_precision',
        'minimum_quantity',
        'price_precision',
        'minimum_amount',
        'is_enable',
        'is_show_beta_tester',
        'zone',
		'release_time',
		'market_price'
    ];

    public function coinConfirmation() {
        return $this->hasOne(CoinsConfirmation::class, 'coin', 'coin');
    }

    public function getIsPairActiveAttribute()
	{
		return !$this->release_time || $this->release_time <= Utils::currentMilliseconds();
	}
}
