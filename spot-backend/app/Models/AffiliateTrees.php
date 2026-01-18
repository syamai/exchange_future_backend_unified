<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateTrees extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function userDown() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function userUp() {
        return $this->belongsTo(User::class, 'referrer_id', 'id');
    }

    public function reportTransactions() {
        return $this->hasMany(ReportTransaction::class, 'user_id', 'user_id');
    }

    public function reportTransactionsUp()
    {
        return $this->hasMany(ReportTransaction::class, 'user_id', 'referrer_id');
    }

}

