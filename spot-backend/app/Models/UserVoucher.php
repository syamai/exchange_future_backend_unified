<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserVoucher extends Model
{
    use HasFactory;

    public $table = 'user_vouchers';

    public $fillable = [
        'voucher_id',
        'user_id',
        'expires_date',
        'status',
        'conditions_use_old',
        'amount_old',
    ];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }
}
