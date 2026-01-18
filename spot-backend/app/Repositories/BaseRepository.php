<?php

namespace App\Repositories;

class BaseRepository
{
    /**
     * Configure the Model
     **/
    public function model()
    {
        return null;
    }

    public function create($input)
    {
        return $this->model()->insertOne($input); // <== TODO fix
    }
}
