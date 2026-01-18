<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Consts;

class MamRequest extends Model
{
    public $table = 'mam_requests';
    public $fillable = [
        'user_id',
        'master_id',
        'type',
        'status',
        'amount',
        'revoke_type',
        'note',
        'timestamp',
    ];

    public function scopeGetRevoke($query)
    {
        return $query->where('type', Consts::MAM_REQUEST_REVOKE);
    }

    public function scopeGetApproved($query)
    {
        return $query->where('status', Consts::MAM_STATUS_APPROVED);
    }

    public function isJoinAssignRequest()
    {
        return ($this->type === Consts::MAM_REQUEST_JOIN || $this->type === Consts::MAM_REQUEST_ASSIGN);
    }
}
