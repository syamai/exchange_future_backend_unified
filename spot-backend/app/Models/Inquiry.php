<?php

namespace App\Models;

use App\Consts;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    use HasFactory;

    public $fillable = [
        'type_id',
        'title',
        'question',
        'user_id',
        'reply_id',
        'answer',
        'reply_at',
        'status'
    ];

    protected $appends = ['status_text'];
    protected $casts = [
        'reply_at' => 'datetime',
    ];

    public function reply()
    {
        return $this->belongsTo(Admin::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function inquiryType()
    {
        return $this->belongsTo(InquiryType::class, 'type_id', 'id');
    }

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            Consts::INQUIRY_STATUS_PENDING => 'Pending',
            Consts::INQUIRY_STATUS_REPLIED => 'Replied',
            default => 'cancel',
        };
    }

    public function scopeFilter($query, $input)
    {
        if (!empty($input['title'])) {
            $query->where('title', 'LIKE', "%{$input['title']}%");
        }
        if (!empty($input['type_id'])) {
            $query->where('type_id', $input['type_id']);
        }
        /*if (!empty($input['email'])) {
            $email = $input['email'];
            $query->whereHas('user', function ($query) use ($email) {
                $query->where('email', 'like', "%{$email}%");
            });
        }*/
        return $query;
    }

    public function scopeUserWithWhereHas($query, $email = "")
    {
        if ($email) {
            return $query->whereHas('user', fn($builder) => $builder->where(
                function ($q) use ($email) {
                    $q->orWhere('email', 'LIKE', "%{$email}%");
                }))
                ->with(['user' => fn($builder) => $builder->where(
                    function ($q) use ($email) {
                        $q->orWhere('email', 'LIKE', "%{$email}%");
                    })
                    ->select(['id', 'uid', 'name', 'email'])
                ]);
        }
        return $query->with('user:id,uid,name,email');

    }
}
