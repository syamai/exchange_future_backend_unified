<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'feature',
        'action'
    ];

    public function object()
    {
        return $this->morphTo()->withTrashed();
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }

    public function scopeFilter($query, $input)
    {
        if (!empty($input['action'])) {
            $query->where('action', 'LIKE', "%{$input['title']}%");
        }

        return $query;
    }
}
