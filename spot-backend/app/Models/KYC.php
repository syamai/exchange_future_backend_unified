<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KYC extends Model
{
    public $table = 'user_kyc';

    public $fillable = [
        'full_name',
        'id_front',
        'id_back',
        'id_selfie',
        'gender',
        'country',
        'id_number',
        'user_id',
        'status',
        'bank_status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getIdFrontAttribute($value)
    {
        return $this->getImageUrl($value);
    }

    public function getIdBackAttribute($value)
    {
        return $this->getImageUrl($value);
    }

    public function getIdSelfieAttribute($value)
    {
        return $this->getImageUrl($value);
    }

    protected function getImageUrl($value)
    {
        if (Str::startsWith($value, 'http')) {
            return $value;
        }
        return env('API_URL', '') . '/'. $value;
    }
}
