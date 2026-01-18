<?php

namespace App\Models;

use App\Traits\UsesUnixTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAssetCommissionRates extends Model
{
    use HasFactory;
    use UsesUnixTimestamps;
    protected $guarded = [];
    protected $table = 'user_asset_commission_rates';

    public $timestamps = false;
}
