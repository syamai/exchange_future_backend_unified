<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiquidationCommissionDetail extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function parent()
    {
        return $this->belongsTo(LiquidationCommission::class);
    }

    public function scopeUserWithWhereHas($query, $search = "")
    {
        if ($search) {
            return $query->whereHas('user', fn($builder) => $builder->where(
                function ($q) use ($search) {
                    $q->orWhere('id', $search);
                    $q->orWhere('email', 'LIKE', "%{$search}%");
                }))
                ->with(['user' => fn($builder) => $builder->where(
                    function ($q) use ($search) {
                        $q->orWhere('id', $search);
                        $q->orWhere('email', 'LIKE', "%{$search}%");
                    })
                    ->select(['id', 'name', 'email'])
                ]);
        }
        return $query->with('user:id,name,email');

    }
}
