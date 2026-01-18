<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserKyc extends Model
{
    protected $table = 'user_kyc';

    const UNVERIFIED_STATUS = 'unverified';
    const VERIFYING_STATUS = 'verifying';
    const PENDING_STATUS = 'pending';
    const VERIFIED_STATUS = 'verified';
    const REJECTED_STATUS = 'rejected';

    public $fillable = [
        'user_id',
        'status',
    ];

    public function files()
    {
        return $this->hasMany(KycFile::class);
    }

    public function userInfo()
    {
        return $this->hasOne(UserInformation::class, 'user_id', 'user_id');
    }
}
