<?php

namespace App\Models;

use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiquidationCommission extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $appends = ['amount_receive', 'is_submit'];
    protected $casts = [
        'complete_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->hasMany(LiquidationCommissionDetail::class);
    }

    public function getIsSubmitAttribute()
    {
        return strtotime(Carbon::now()->subHours(2)->toDateString()) > strtotime($this->date);
    }

    public function getAmountReceiveAttribute()
    {
        return BigNumber::round(BigNumber::new($this->amount)->mul($this->rate)->div(100), BigNumber::ROUND_MODE_HALF_UP, 6);
    }

    public function scopeUserWithWhereHas($query, $search = "")
    {
        if ($search) {
            return $query->whereHas('user', fn($builder) => $builder->where(
                function ($q) use ($search) {
                    // $q->orWhere('email', 'LIKE', "%{$search}%");
                    $q->orWhere('email', 'LIKE', "{$search}");
                    $q->orWhere('id', $search);
                }))
                ->with(['user' => fn($builder) => $builder->where(
                    function ($q) use ($search) {
                        $q->orWhere('email', 'LIKE', "{$search}"); //$q->orWhere('email', 'LIKE', "%{$search}%");
                        $q->orWhere('id', $search);
                    })
                    ->select(['id', 'name', 'email'])
                ]);
        }
        return $query->with('user:id,name,email');

    }
}
