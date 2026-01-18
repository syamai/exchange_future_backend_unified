<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferrerClientLevel extends Model
{
    use HasFactory;
    protected $table = 'referrer_client_levels';
    protected $primaryKey = 'level';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['trade_min', 'volume', 'rate', 'label'];

    public static function filterProgress(array $exclude = ['created_at', 'updated_at'])
    {
        return self::all()->map(fn($item) => collect($item->toArray())->except($exclude));
    }
}
