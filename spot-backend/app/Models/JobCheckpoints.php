<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobCheckpoints extends Model
{
    use HasFactory;
    public $incrementing = false;

    protected $keyType = 'string';
    protected $primaryKey = 'job';
    protected $fillable = [
        'job',
        'last_calculated_at',
    ];

    public function scopeJobName($query, $jobName)
    {
        return $query->where('job', $jobName);
    }
}
