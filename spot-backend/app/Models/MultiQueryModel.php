<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MultiQueryModel extends Model
{

    public function getAttributes()
    {
        return $this->attributes;
    }
}
