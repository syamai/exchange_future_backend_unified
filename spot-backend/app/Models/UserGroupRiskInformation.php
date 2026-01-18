<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGroupRiskInformation extends Model
{
    protected $table = 'user_group_risk_information';

    protected $fillable = ['user_id','email','account_id','is_mam', 'balance', 'unrealised_pnl'];
}
