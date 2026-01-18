<?php

namespace App\Repositories;

use App\Models\Order;

class OrderRepository extends BaseRepository
{
    /**
     * Configure the Model
     **/
    public function model()
    {
        return Order::class;
    }
}
