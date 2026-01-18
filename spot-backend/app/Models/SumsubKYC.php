<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SumsubKYC extends Model
{
    public $table = 'user_samsub_kyc';

    public $fillable = [
        'first_name',
        'last_name',
        'full_name',
        'id_front',
        'id_back',
        'id_selfie',
        'gender',
        'country',
        'id_number',
        'user_id',
        'status',
        'bank_status',
        'id_applicant',
		'review_result'
    ];

    public function scopeTableName() {
        return $this->getTable();
    }

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
        return $value ?? "";
    }
}
