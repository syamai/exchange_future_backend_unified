<?php

namespace App\Models;

use App\Traits\UsesUnixTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAssetSnapshots extends Model
{
    use HasFactory;
    use UsesUnixTimestamps;
    protected $guarded = [];
    protected $table = 'user_asset_snapshots';

    public $timestamps = false;
}
