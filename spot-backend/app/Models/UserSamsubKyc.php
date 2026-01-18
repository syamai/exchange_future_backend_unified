<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSamsubKyc extends Model
{
    protected $table = 'user_samsub_kyc';

    const UNVERIFIED_STATUS = 'unverified';
    const VERIFYING_STATUS = 'verifying';
    const PENDING_STATUS = 'pending';
    const VERIFIED_STATUS = 'verified';
    const REJECTED_STATUS = 'rejected';

    public $fillable = [
        'user_id',
        'id_applicant',
        'status',
    ];

    public function userInfo()
    {
        return $this->hasOne(UserInformation::class, 'user_id', 'user_id');
    }
    public function user() {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
