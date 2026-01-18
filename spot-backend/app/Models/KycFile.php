<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycFile extends Model
{
    const SELFIE_ID = 'selfie_id';
    const PASSPORT = 'passport';
    const DOCUMENT = 'document';

    protected $fillable = [
        'user_kyc_id',
        'metadata',
        'id_no',
        'path',
    ];
}
