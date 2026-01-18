<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRegisterSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'referrer_code',
        'referrer'
    ];

	public function user()
	{
		return $this->belongsTo(User::class, 'user_id', 'id');
	}

	public function scopeFilter($query, $input)
	{

		if (!empty($input['created_from']) && !empty($input['created_to'])) {
			$query->whereBetween('created_at', [Carbon::createFromTimestampMs($input['created_from']), Carbon::createFromTimestampMs($input['created_to'])]);
		}

		if (!empty($input['is_source'])) {
			$query->whereNotNull('utm_source');
		}

		if (!empty($input['is_medium'])) {
			$query->whereNotNull('utm_medium');
		}

		if (!empty($input['is_campaign'])) {
			$query->whereNotNull('utm_campaign');
		}

		if (!empty($input['is_referrer_code'])) {
			$query->whereNotNull('referrer_code');
		}

		if (!empty($input['is_referrer'])) {
			$query->whereNotNull('referrer');
		}

		return $query;
	}

	public function scopeUserWithWhereHas($query, $s = "")
	{
		if ($s) {
			return $query->whereHas('user', fn($builder) => $builder->where(
				function ($q) use ($s) {
					$q->orWhere('email', 'LIKE', "%{$s}%");
					$q->orWhere('uid', 'LIKE', "%{$s}%");
				}))
				->with(['user' => fn($builder) => $builder->where(
					function ($q) use ($s) {
						$q->orWhere('email', 'LIKE', "%{$s}%");
						$q->orWhere('uid', 'LIKE', "%{$s}%");
					})
					->select(['id', 'uid', 'name', 'email'])
				]);
		}
		return $query->with('user:id,uid,name,email');

	}
}
