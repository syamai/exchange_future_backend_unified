<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminKrwBankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_id',
        'account_no',
        'account_name',
        'status',
        'note',
    ];


    public function bankName()
    {
        return $this->belongsTo(BankName::class, 'bank_id', 'id');
    }
}
