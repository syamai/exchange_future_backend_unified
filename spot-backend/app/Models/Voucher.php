<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use HasFactory;
    use SoftDeletes;

    use SoftDeletes;

    public $table = 'vouchers';
    protected $dates = ['deleted_at'];
    public $fillable = [
        'name',
        'type',
        'currency',
        'amount',
        'status',
        'expires_date',
        'number',
        'conditions_use',
        'expires_date_number'
    ];

}
