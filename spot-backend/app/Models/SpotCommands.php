<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpotCommands extends Model
{
    use HasFactory;
    public $fillable = [
        'command_key',
        'type_name',
        'user_id',
        'obj_id',
        'payload',
        'status',
        'payload_result'
    ];
}
