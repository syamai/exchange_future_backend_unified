<?php

namespace App\Models;

use App\Traits\UsesUnixTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAssetTransactions extends Model
{
    use HasFactory;
    use UsesUnixTimestamps;
    protected $guarded = [];
    protected $table = 'user_asset_transactions';

    public $timestamps = false;
}
